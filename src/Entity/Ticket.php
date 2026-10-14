<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TicketRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\Table(name: 'ticket')]
class Ticket
{
    public const array PRIORITIES = ['low', 'normal', 'high'];
    public const array STATUSES = ['open', 'in_progress', 'closed'];
    public const array SLA_TARGET_HOURS = ['low' => 72, 'normal' => 24, 'high' => 8];
    public const string SLA_DEFAULT_PRIORITY = 'normal';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(length: 16)]
    private string $priority;

    #[ORM\Column(length: 16)]
    private string $status = 'open';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\ManyToOne(targetEntity: Agent::class)]
    #[ORM\JoinColumn(name: 'assignee_id', nullable: true, onDelete: 'SET NULL')]
    private ?Agent $assignee = null;

    public function __construct(string $title, string $description, string $priority, ?\DateTimeImmutable $createdAt = null, ?\DateTimeImmutable $dueAt = null)
    {
        $this->title = $title;
        $this->description = $description;
        $this->priority = $priority;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->dueAt = $dueAt ?? $this->createdAt->modify(sprintf('+%d hours', self::slaTargetHours($priority)));
    }

    public static function slaTargetHours(string $priority): int
    {
        return self::SLA_TARGET_HOURS[$priority] ?? self::SLA_TARGET_HOURS[self::SLA_DEFAULT_PRIORITY];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function getAssignee(): ?Agent
    {
        return $this->assignee;
    }

    public function setAssignee(?Agent $assignee): void
    {
        $this->assignee = $assignee;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'dueAt' => $this->dueAt?->format(DATE_ATOM),
            'assignee' => null,
        ];
    }
}
