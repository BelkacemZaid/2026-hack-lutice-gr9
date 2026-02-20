<?php

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use App\Entity\Subscription;
use App\Enum\OfferType;
use App\Enum\StatusEnum;
use App\Form\SubscriptionType;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
// CETTE LIGNE EST ESSENTIELLE POUR LANCER LE SCRIPT VPS
use Symfony\Component\Process\Process;

class SubscriptionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SubscriptionRepository $subscriptionRepository
    ) {}

    /**
     * Étape 1 : Affichage des forfaits
     */
    #[Route('/', name: 'app_offres')]
    public function offres(): Response
    {
        return $this->render('portal/offres.html.twig');
    }

    /**
     * Étape 2 : Formulaire de souscription
     */
    #[Route('/formulaire/{offer}', name: 'app_formulaire')]
    public function formulaire(string $offer, Request $request): Response
    {
        $offerEnum = OfferType::tryFrom($offer);

        if (!$offerEnum) {
            return $this->redirectToRoute('app_offres');
        }

        $subscription = new Subscription();
        $subscription->setOfferType($offerEnum);

        $form = $this->createForm(SubscriptionType::class, $subscription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Génération du sous-domaine
            $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '', $subscription->getClientName()));
            $subscription->setDomain($cleanName . '.lutice.com');

            $subscription->setStatus(StatusEnum::PENDING);

            $this->em->persist($subscription);
            $this->em->flush();

            // Gestion du bouton "Simuler" ou "Payer"
            $action = $request->request->get('payment_action');
            if ($action === 'simulate') {
                return $this->redirectToRoute('app_paiement_process', ['id' => $subscription->getId()]);
            }

            return $this->redirectToRoute('app_paiement_stripe', ['id' => $subscription->getId()]);
        }

        return $this->render('portal/formulaire.html.twig', [
            'form' => $form->createView(),
            'offer' => $offer
        ]);
    }

    /**
     * Étape 3 : Session de paiement Stripe
     */
    #[Route('/paiement/stripe/{id}', name: 'app_paiement_stripe')]
    public function payWithStripe(Subscription $subscription, UrlGeneratorInterface $router, #[Autowire('%env(STRIPE_SECRET_KEY)%')] string $stripeSecretKey): Response
    {
        Stripe::setApiKey($stripeSecretKey);

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => ['name' => 'Offre Lutice ' . $subscription->getOfferType()->value],
                    'unit_amount' => ($subscription->getOfferType()->value === 'start2') ? 5000 : 20000,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $router->generate('app_paiement_process', ['id' => $subscription->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'cancel_url' => $router->generate('app_offres', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        return $this->redirect($session->url, 303);
    }

    /**
     * Étape 4 : Déclenchement de l'Infrastructure (Lancement du script)
     */
    #[Route('/paiement-process/{id}', name: 'app_paiement_process')]
    public function processPayment(Subscription $subscription): Response
    {
        // 1. On informe que le serveur est en cours de création
        $subscription->setStatus(StatusEnum::PROVISIONING);
        $this->em->flush();

        // 2. ON APPELLE LE SCRIPT VPS DE TES COLLÈGUES
        // On lui envoie le nom du client, l'offre et l'UUID
        $process = new Process([
            'php',
            $this->getParameter('kernel.project_dir') . '/vps.php',
            $subscription->getClientName(),
            $subscription->getOfferType()->value,
            $subscription->getId()->toString()
        ]);

        // On le lance en arrière-plan (le client n'attend pas que le serveur soit fini)
        $process->start();

        return $this->redirectToRoute('app_suivi', ['id' => $subscription->getId()]);
    }

    /**
     * Étape finale : Webhook de réception de l'IP
     */
    #[Route('/api/callback/deploy-ready/{id}', name: 'api_deploy_callback', methods: ['POST'])]
    public function deployCallback(Subscription $subscription, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['ip'])) {
            // On enregistre l'IP envoyée par le script réseau
            $subscription->setPublicIp($data['ip']);
        }

        // On dit que tout est prêt !
        $subscription->setStatus(StatusEnum::READY);
        $this->em->flush();

        return $this->json(['message' => 'Le portail a bien reçu l\'adresse IP.']);
    }

    #[Route('/suivi/{id}', name: 'app_suivi')]
    public function suivi(Subscription $subscription): Response
    {
        return $this->render('portal/suivi.html.twig', [
            'subscription' => $subscription
        ]);
    }

    #[Route('/api/status/{id}', name: 'api_status', methods: ['GET'])]
    public function apiStatus(Subscription $subscription): JsonResponse
    {
        return $this->json([
            'status' => $subscription->getStatus()->value,
            'url' => 'https://' . $subscription->getDomain(),
            'errorMessage' => $subscription->getErrorMessage()
        ]);
    }

    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(
        Request $request,
        #[Autowire('%env(ADMIN_USERNAME)%')] string $adminUser,
        #[Autowire('%env(ADMIN_PASSWORD)%')] string $adminPass
    ): Response {

        $user = $request->headers->get('php-auth-user');
        $pass = $request->headers->get('php-auth-pw');

        if ($user !== $adminUser || $pass !== $adminPass) {
            $response = new Response('Accès non autorisé', 401);
            $response->headers->set('WWW-Authenticate', 'Basic realm="Espace Administrateur Lutice"');
            return $response;
        }

        $subscriptions = $this->subscriptionRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('portal/admin_dashboard.html.twig', [
            'subscriptions' => $subscriptions
        ]);
    }

    #[Route('/api/config/{id}', name: 'api_subscription_config', methods: ['GET'])]
    public function getConfig(Subscription $subscription): JsonResponse
    {
        return $this->json([
            'id' => $subscription->getId(),
            'client_name' => $subscription->getClientName(),
            'domain' => $subscription->getDomain(),
            'offer' => $subscription->getOfferType()->value,
            'logo_url' => $subscription->getLogoPublicUrl(),
        ]);
    }
}
