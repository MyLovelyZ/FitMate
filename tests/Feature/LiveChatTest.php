<?php

/**
 * Live chat pelanggan ↔ CS: percakapan dibuat saat pesan pertama, CS membalas
 * dan otomatis jadi penanggung jawab, status dibaca, dan penutupan percakapan.
 */

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->customer = User::factory()->create(['name' => 'Rani']);
    $this->cs = User::factory()->customerService()->create(['name' => 'Dewi CS']);
});

it('starts a conversation with the first message only', function () {
    Livewire::actingAs($this->customer)
        ->test('pages::user-chat')
        ->assertSee('Belum ada pesan');

    expect(ChatConversation::count())->toBe(0);

    Livewire::actingAs($this->customer)
        ->test('pages::user-chat')
        ->set('body', 'Kemeja ukuran M masih ada?')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('body', '')
        ->assertSee('Kemeja ukuran M masih ada?')
        ->assertSee('wire:poll', false);

    $conversation = ChatConversation::sole();

    expect($conversation->user_id)->toBe($this->customer->id)
        ->and($conversation->isOpen())->toBeTrue()
        ->and($conversation->last_message_at)->not->toBeNull();
});

it('reuses the open conversation and rejects empty messages', function () {
    $component = Livewire::actingAs($this->customer)->test('pages::user-chat');

    $component->set('body', 'Halo')->call('send');
    $component->set('body', 'Masih ada orang?')->call('send');
    $component->set('body', '')->call('send')->assertHasErrors('body');

    expect(ChatConversation::count())->toBe(1)
        ->and(ChatMessage::count())->toBe(2);
});

it('lets CS read and reply, which assigns the conversation and marks it read', function () {
    $conversation = ChatConversation::forCustomer($this->customer);
    $conversation->send($this->customer, 'Paket saya belum sampai.');

    Livewire::actingAs($this->cs)
        ->test('pages::cs-chats')
        ->assertSee('Rani')
        ->assertSee('pesan belum dibaca', false)
        ->call('select', $conversation->id)
        ->assertSee('Paket saya belum sampai.')
        ->set('body', 'Baik, kami cek resinya ya.')
        ->call('send')
        ->assertHasNoErrors();

    $conversation->refresh();

    expect($conversation->assigned_to)->toBe($this->cs->id)
        ->and($conversation->unreadCountFor($this->cs))->toBe(0)
        ->and($conversation->unreadCountFor($this->customer))->toBe(1);

    Livewire::actingAs($this->customer)
        ->test('pages::user-chat')
        ->assertSee('Baik, kami cek resinya ya.')
        ->assertSee('Dewi CS');

    expect($conversation->unreadCountFor($this->customer))->toBe(0);
});

it('lets CS close a conversation, and the next message opens a new one', function () {
    $conversation = ChatConversation::forCustomer($this->customer);
    $conversation->send($this->customer, 'Terima kasih!');

    Livewire::actingAs($this->cs)
        ->test('pages::cs-chats', ['c' => $conversation->id])
        ->set('conversationId', $conversation->id)
        ->call('close')
        ->assertDispatched('notify');

    expect($conversation->fresh()->isOpen())->toBeFalse();

    Livewire::actingAs($this->customer)
        ->test('pages::user-chat')
        ->assertSee('Percakapan ditutup CS')
        ->set('body', 'Satu pertanyaan lagi')
        ->call('send');

    expect(ChatConversation::count())->toBe(2)
        ->and(ChatConversation::where('status', 'open')->sole()->messages()->sole()->body)->toBe('Satu pertanyaan lagi');
});

it('opens a chat with a buyer from a ticket link', function () {
    Livewire::withQueryParams(['user' => $this->customer->id])
        ->actingAs($this->cs)
        ->test('pages::cs-chats')
        ->assertSet('conversationId', ChatConversation::sole()->id)
        ->assertSee($this->customer->email);
});

it('keeps chat pages to the right roles', function () {
    $this->actingAs($this->customer)->get(route('cs.chats.index'))->assertRedirect('/');
    $this->actingAs($this->cs)->get(route('user.chat'))->assertRedirect('/');
    $this->actingAs($this->customer)->get(route('user.chat'))->assertOk();
    $this->actingAs($this->cs)->get(route('cs.chats.index'))->assertOk();
});
