<?php

namespace App\Entity;

use App\Enum\OfferType;
use App\Enum\StatusEnum;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[Vich\Uploadable]
#[UniqueEntity(fields: ['clientName'], message: 'Ce nom de client est déjà utilisé')]
#[UniqueEntity('clientEmail')]
class Subscription
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[Assert\NotBlank(message: "Le nom de l'entreprise est obligatoire.")]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: "Le nom doit faire au moins {{ limit }} caractères.",
        maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractère<s."
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9-]+$/',
        message: "Le nom ne doit contenir que des lettres sans accents, des chiffres et des tirets (pas d'espaces)."
    )]
    #[Assert\NotEqualTo(value: 'admin', message: 'Ce nom de domaine est réservé par le système.')]
    #[Assert\NotEqualTo(value: 'api', message: 'Ce nom de domaine est réservé par le système.')]
    #[Assert\NotEqualTo(value: 'www', message: 'Ce nom de domaine est réservé par le système.')]
    #[ORM\Column(length: 30, unique: true)]
    private ?string $clientName = null;


    #[ORM\Column(length: 255)]
    private string $clientEmail;

    #[ORM\Column(type: 'string', enumType: OfferType::class)]
    private OfferType $offerType;

    #[ORM\Column(length: 255)]
    private string $domain;

    #[ORM\Column(type: 'string', enumType: StatusEnum::class)]
    private StatusEnum $status;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $scalewayServerId = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $publicIp = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $logoName = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $logoSize = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $logoMimeType = null;

    #[Vich\UploadableField(
        mapping: 'subscription_logo',
        fileNameProperty: 'logoName',
        size: 'logoSize',
        mimeType: 'logoMimeType'
    )]
    #[Assert\Image(
        maxSize: '2M',
        mimeTypes: ['image/png', 'image/jpeg'],
        maxSizeMessage: 'Le logo ne doit pas dépasser {{ limit }} {{ suffix }}.',
        mimeTypesMessage: 'Le logo doit être au format PNG ou JPG.'
    )]
    private ?File $logoFile = null;

    public function __construct()
    {
        $this->id = Uuid::v4();  // ✅ Parfait avec UUID !
        $this->status = StatusEnum::PENDING;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function setId(Uuid $id): void { $this->id = $id; }

    public function getClientName(): string { return $this->clientName; }
    public function setClientName(string $clientName): void { $this->clientName = $clientName; }

    public function getClientEmail(): string { return $this->clientEmail; }
    public function setClientEmail(string $clientEmail): void { $this->clientEmail = $clientEmail; }

    public function getOfferType(): OfferType { return $this->offerType; }
    public function setOfferType(OfferType $offerType): void { $this->offerType = $offerType; }

    public function getDomain(): string { return $this->domain; }
    public function setDomain(string $domain): void { $this->domain = $domain; }

    public function getStatus(): StatusEnum { return $this->status; }
    public function setStatus(StatusEnum $status): void { $this->status = $status; }

    public function getScalewayServerId(): ?string { return $this->scalewayServerId; }
    public function setScalewayServerId(?string $scalewayServerId): void { $this->scalewayServerId = $scalewayServerId; }

    public function getPublicIp(): ?string { return $this->publicIp; }
    public function setPublicIp(?string $publicIp): void { $this->publicIp = $publicIp; }

    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $errorMessage): void { $this->errorMessage = $errorMessage; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): void { $this->createdAt = $createdAt; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void { $this->updatedAt = $updatedAt; }

    public function getLogoFile(): ?File
    {
        return $this->logoFile;
    }
    public function getLogoPublicUrl(): ?string
    {
        if (!$this->logoName) return null;
        // Remplace par ton vrai domaine de dev pour les tests
        return "http://127.0.0.1:8000/logos/" . $this->logoName;
    }

    public function setLogoFile(?File $logoFile): void
    {
        $this->logoFile = $logoFile;
        if (null !== $logoFile) {
            // Met à jour automatiquement size et mimeType
            $this->logoSize = $logoFile->getSize();
            $this->logoMimeType = $logoFile->getMimeType();
        }
    }

    public function getLogoName(): ?string
    {
        return $this->logoName;
    }

    public function setLogoName(?string $logoName): void
    {
        $this->logoName = $logoName;
    }

    public function getLogoSize(): ?int
    {
        return $this->logoSize;
    }

    public function setLogoSize(?int $logoSize): void
    {
        $this->logoSize = $logoSize;
    }

    public function getLogoMimeType(): ?string
    {
        return $this->logoMimeType;
    }

    public function setLogoMimeType(?string $logoMimeType): void
    {
        $this->logoMimeType = $logoMimeType;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoName ? '/logos/' . $this->logoName : null;
    }
}
