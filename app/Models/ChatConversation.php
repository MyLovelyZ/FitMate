<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ChatConversation extends Model
{
    use HasFactory;

    /**
     * Role yang menjawab chat dari sisi FitMate (sama dengan middleware `cs`).
     *
     * @var array<int, string>
     */
    public const STAFF_ROLES = ['customerservice', 'admin'];

    protected $fillable = [
        'user_id',
        'assigned_to',
        'status',
        'last_message_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Percakapan yang masih terbuka milik pelanggan ini, atau yang baru.
     */
    public static function forCustomer(User $customer): self
    {
        return DB::transaction(fn (): self => static::where('user_id', $customer->id)
            ->where('status', 'open')
            ->lockForUpdate()
            ->latest('id')
            ->first()
            ?? static::create(['user_id' => $customer->id, 'status' => 'open']));
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Mengirim pesan. Staf yang membalas otomatis jadi penanggung jawab
     * percakapan kalau belum ada.
     */
    public function send(User $sender, string $body): ChatMessage
    {
        return DB::transaction(function () use ($sender, $body): ChatMessage {
            $message = $this->messages()->create([
                'sender_id' => $sender->id,
                'body' => trim($body),
            ]);

            $this->update([
                'last_message_at' => $message->created_at,
                'assigned_to' => $this->assigned_to ?? (in_array($sender->role, self::STAFF_ROLES, true) ? $sender->id : null),
            ]);

            return $message;
        });
    }

    /**
     * Menandai pesan dari pihak lawan bicara sebagai sudah dibaca.
     */
    public function markReadBy(User $reader): void
    {
        $this->messages()
            ->where('sender_id', '!=', $reader->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Jumlah pesan yang belum dibaca oleh `$reader`.
     */
    public function unreadCountFor(User $reader): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $reader->id)
            ->whereNull('read_at')
            ->count();
    }

    public function close(): void
    {
        $this->update(['status' => 'closed', 'closed_at' => now()]);
    }

    // relationships

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at')->orderBy('id');
    }
}
