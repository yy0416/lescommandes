<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'order.error.name_required')]
    private ?string $customerName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Please enter your phone number')]
    #[Assert\Regex(
        pattern: '/^(0[1-7])[0-9]{8}$/',
        message: 'Please enter a valid French phone number (e.g., 0612345678)'
    )]
    private ?string $phone = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'order.error.email_required')]
    #[Assert\Email(message: 'order.error.email_invalid')]
    private ?string $email = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: '请选择取货地点')]
    private ?PickupLocation $pickupLocation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: '请选择取货时间')]
    #[Assert\GreaterThan([
        'value' => '+24 hours',
        'message' => '取货时间必须至少提前24小时预约'
    ])]
    private ?\DateTimeInterface $pickupTime = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $totalAmount = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist', 'remove'])]
    private Collection $orderItems;

    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): self
    {
        $this->customerName = $customerName;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function getPickupTime(): ?\DateTimeInterface
    {
        return $this->pickupTime;
    }

    public function setPickupTime(\DateTimeInterface $pickupTime): self
    {
        // 确保时间是巴黎时区
        if ($pickupTime instanceof \DateTime) {
            $pickupTime->setTimezone(new \DateTimeZone('Europe/Paris'));
        }
        $this->pickupTime = $pickupTime;
        return $this;
    }

    public function getPickupLocation(): ?PickupLocation
    {
        return $this->pickupLocation;
    }

    public function setPickupLocation(?PickupLocation $pickupLocation): self
    {
        $this->pickupLocation = $pickupLocation;
        return $this;
    }

    public function getTotalAmount(): ?float
    {
        return $this->totalAmount / 100;
    }

    public function setTotalAmount(float $totalAmount): self
    {
        $this->totalAmount = (int)($totalAmount * 100);
        return $this;
    }

    public function calculateTotal(): void
    {
        $total = 0;
        foreach ($this->orderItems as $item) {
            $total += $item->getSubtotal() * 100; // 转换为分
        }
        $this->totalAmount = $total;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): self
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            $orderItem->setOrder($this);
        }

        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): self
    {
        if ($this->orderItems->removeElement($orderItem)) {
            // set the owning side to null (unless already changed)
            if ($orderItem->getOrder() === $this) {
                $orderItem->setOrder(null);
            }
        }

        return $this;
    }
}
