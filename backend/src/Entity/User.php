<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]

class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $loginCode = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $loginCodeExpiresAt = null;

    /**
     * @var Collection<int, ApiToken>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ApiToken::class)]
    private Collection $apiTokens;

    public function __construct()
    {
        $this->apiTokens = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * The unique identifier Symfony security uses to represent this user.
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * No sensitive credentials are stored (passwordless auth), so nothing to erase.
     */
    public function eraseCredentials(): void
    {
    }

    public function getLoginCode(): ?string 
    {
        return $this->loginCode;
    }

    public function setLoginCode(?string $code): static 
    { 
        $this->loginCode = $code; 
        return $this; 
    }

    public function getLoginCodeExpiresAt(): ?\DateTimeImmutable 
    { 
        return $this->loginCodeExpiresAt;
    }

    public function setLoginCodeExpiresAt(?\DateTimeImmutable $at): static 
    { 
        $this->loginCodeExpiresAt = $at; return $this;
    }

    /**
     * @return Collection<int, ApiToken>
     */
    public function getApiTokens(): Collection
    {
        return $this->apiTokens;
    }

    public function addApiToken(ApiToken $apiToken): static
    {
        if (!$this->apiTokens->contains($apiToken)) {
            $this->apiTokens->add($apiToken);
            $apiToken->setUser($this);
        }

        return $this;
    }

    public function removeApiToken(ApiToken $apiToken): static
    {
        $this->apiTokens->removeElement($apiToken);

        return $this;
    }
}
