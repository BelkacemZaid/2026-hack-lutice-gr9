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

class SubscriptionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SubscriptionRepository $subscriptionRepository
    ) {}

    /**
     * Étape 1 : Affichage des forfaits (F-PORT-01)
     */
    #[Route('/', name: 'app_offres')]
    public function offres(): Response
    {
        return $this->render('portal/offres.html.twig');
    }

    /**
     * Étape 2 : Formulaire de souscription (F-PORT-02)
     */
    #[Route('/formulaire/{offer}', name: 'app_formulaire')]
    public function formulaire(string $offer, Request $request): Response
    {
        // On vérifie que l'offre existe dans l'Enum (Exigence F-CUST-05)
        $offerEnum = OfferType::tryFrom($offer);

        if (!$offerEnum) {
            return $this->redirectToRoute('app_offres');
        }

        $subscription = new Subscription();
        $subscription->setOfferType($offerEnum); // Assignation de l'Enum

        $form = $this->createForm(SubscriptionType::class, $subscription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Génération du sous-domaine (Exigence F-PORT-02)
            $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '', $subscription->getClientName()));
            $subscription->setDomain($cleanName . '.lutice.com');

            // Statut initial avant paiement
            $subscription->setStatus(StatusEnum::PENDING);

            $this->em->persist($subscription);
            $this->em->flush();

            // --- GESTION DES DEUX BOUTONS ---
            $action = $request->request->get('payment_action');

            if ($action === 'simulate') {
                // Si le bouton "Simuler" a été cliqué, on va direct au process (sans payer)
                return $this->redirectToRoute('app_paiement_process', ['id' => $subscription->getId()]);
            }

            // Sinon (par défaut ou si clic sur Stripe), on redirige vers Stripe
            return $this->redirectToRoute('app_paiement_stripe', ['id' => $subscription->getId()]);
        }

        return $this->render('portal/formulaire.html.twig', [
            'form' => $form->createView(),
            'offer' => $offer
        ]);
    }

    /**
     * Étape 3 (NOUVEAU) : Création de la session Stripe
     */
    #[Route('/paiement/stripe/{id}', name: 'app_paiement_stripe')]
    public function payWithStripe(Subscription $subscription, UrlGeneratorInterface $router, #[Autowire('%env(STRIPE_SECRET_KEY)%')] string $stripeSecretKey): Response
    {
        // CORRECTION : On utilise la variable injectée au lieu de $_ENV
        Stripe::setApiKey($stripeSecretKey);

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'Offre Lutice ' . $subscription->getOfferType()->value,
                    ],
                    // Prix en centimes
                    'unit_amount' => ($subscription->getOfferType()->value === 'start2') ? 5000 : 20000,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            // SI SUCCÈS : Stripe renvoie le client ici
            'success_url' => $router->generate('app_paiement_process', ['id' => $subscription->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            // SI ANNULÉ : On le renvoie à l'accueil
            'cancel_url' => $router->generate('app_offres', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        return $this->redirect($session->url, 303);
    }

    /**
     * Étape 4 : Validation du paiement et lancement (F-PORT-04)
     * Cette route est appelée par Stripe une fois le paiement validé.
     */
    #[Route('/paiement-process/{id}', name: 'app_paiement_process')]
    public function processPayment(Subscription $subscription): Response
    {
        // Le paiement a réussi sur Stripe (ou a été simulé), on valide en base !
        $subscription->setStatus(StatusEnum::PROVISIONING);
        $this->em->flush();

        // Une fois validé, on envoie le client sur la page de suivi en temps réel
        return $this->redirectToRoute('app_suivi', ['id' => $subscription->getId()]);
    }

    /**
     * Webhook de fin de déploiement pour l'équipe Infra
     */
    #[Route('/api/callback/deploy-ready/{id}', name: 'api_deploy_callback', methods: ['POST'])]
    public function deployCallback(Subscription $subscription, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['ip'])) {
            $subscription->setIpAddress($data['ip']);
            $subscription->setPublicIp($data['ip']);
        }

        $subscription->setStatus(StatusEnum::READY);
        $this->em->flush();

        return $this->json(['message' => 'Statut mis à jour avec succès']);
    }

    /**
     * Étape 5 : Page de suivi (F-PORT-05)
     */
    #[Route('/suivi/{id}', name: 'app_suivi')]
    public function suivi(Subscription $subscription): Response
    {
        return $this->render('portal/suivi.html.twig', [
            'subscription' => $subscription
        ]);
    }

    /**
     * API pour le polling JavaScript (F-PORT-05)
     */
    #[Route('/api/status/{id}', name: 'api_status', methods: ['GET'])]
    public function apiStatus(Subscription $subscription): JsonResponse
    {
        return $this->json([
            'status' => $subscription->getStatus()->value,
            'url' => 'https://' . $subscription->getDomain(),
            'errorMessage' => $subscription->getErrorMessage()
        ]);
    }
    /**
     * Dashboard Admin : Vue d'ensemble de toutes les commandes
     */
    /**
     * Dashboard Admin : Vue d'ensemble de toutes les commandes
     */
    /**
     * Dashboard Admin : Vue d'ensemble de toutes les commandes
     */
    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(
        Request $request,
        #[Autowire('%env(ADMIN_USERNAME)%')] string $adminUser,
        #[Autowire('%env(ADMIN_PASSWORD)%')] string $adminPass
    ): Response {

        $user = $request->headers->get('php-auth-user');
        $pass = $request->headers->get('php-auth-pw');

        // On compare avec les variables sécurisées du .env !
        if ($user !== $adminUser || $pass !== $adminPass) {
            $response = new Response('Accès non autorisé', 401);
            $response->headers->set('WWW-Authenticate', 'Basic realm="Espace Administrateur Lutice"');
            return $response;
        }

        // Si on arrive ici, c'est que le mot de passe est bon
        $subscriptions = $this->subscriptionRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('portal/admin_dashboard.html.twig', [
            'subscriptions' => $subscriptions
        ]);
    }
    /**
     * API pour l'équipe Infra (Pôles 3 & 4)
     * Permet à Ansible de récupérer les infos de personnalisation (Logo, Domaine)
     */
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
