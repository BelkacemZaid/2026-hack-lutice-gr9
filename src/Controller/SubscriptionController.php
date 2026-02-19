<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Enum\OfferType;
use App\Enum\StatusEnum;
use App\Form\SubscriptionType;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
            // On nettoie le nom : minuscules, suppression des caractères spéciaux
            $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '', $subscription->getClientName()));
            $subscription->setDomain($cleanName . '.lutice.com');

            // Statut initial avant paiement
            $subscription->setStatus(StatusEnum::PENDING);

            $this->em->persist($subscription);
            $this->em->flush();

            // Redirection vers le formulaire de paiement (simulé)
            return $this->redirectToRoute('app_paiement_process', ['id' => $subscription->getId()]);
        }

        return $this->render('portal/formulaire.html.twig', [
            'form' => $form->createView(),
            'offer' => $offer
        ]);
    }

    /**
     * Étape 3 : Simulation du paiement (F-PORT-04)
     * Cette route valide le paiement et déclenche virtuellement le provisionnement.
     */
    #[Route('/paiement-process/{id}', name: 'app_paiement_process')]
    public function processPayment(Subscription $subscription): Response
    {
        // Ici, on simule un succès de paiement (Exigence F-PORT-04)
        // On passe le statut à PROVISIONING pour informer l'utilisateur
        $subscription->setStatus(StatusEnum::PROVISIONING);

        $this->em->flush();

        // Une fois payé, on envoie le client sur la page de suivi en temps réel
        return $this->redirectToRoute('app_suivi', ['id' => $subscription->getId()]);
    }

    /**
     * Étape 4 : Page de suivi (F-PORT-05)
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
     * Renvoie le statut actuel en JSON pour mettre à jour la barre de progression.
     */
    #[Route('/api/status/{id}', name: 'api_status', methods: ['GET'])]
    public function apiStatus(Subscription $subscription): JsonResponse
    {
        return $this->json([
            'status' => $subscription->getStatus()->value, // On récupère la valeur de l'Enum string
            'url' => 'https://' . $subscription->getDomain(),
            'errorMessage' => $subscription->getErrorMessage()
        ]);
    }
}
