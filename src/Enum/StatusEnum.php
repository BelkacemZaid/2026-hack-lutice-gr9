<?php

namespace App\Enum;

enum StatusEnum: string
{
    case PENDING = 'pending';           // En attente de paiement
    case PROVISIONING = 'provisioning'; // Création instance Scaleway
    case CONFIGURING = 'configuring';   // Ansible : Config OS + Docker
    case DEPLOYING = 'deploying';       // Ansible : Déploiement Greenlight
    case READY = 'ready';               // Tout est fini, URL accessible
    case ERROR = 'error';               // Problème technique
}
