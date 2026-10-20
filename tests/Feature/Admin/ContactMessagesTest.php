<?php

use App\Enums\ContactMessageStatus;
use App\Enums\Permission;
use App\Models\ContactMessage;
use Inertia\Testing\AssertableInertia as Assert;

it('lists messages newest first, without spam unless asked', function () {
    ContactMessage::factory()->create(['name' => 'Older', 'created_at' => now()->subDay()]);
    ContactMessage::factory()->create(['name' => 'Newer']);
    ContactMessage::factory()->create(['name' => 'Spammer', 'status' => ContactMessageStatus::Spam]);
    $staff = staffWith([Permission::ContactMessagesView]);

    $this->actingAs($staff)
        ->get(route('admin.contact-messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/contact-messages/index')
            ->has('messages.data', 2)
            ->where('messages.data.0.name', 'Newer')
            ->where('counts.new', 2)
            ->where('counts.spam', 1));

    $this->actingAs($staff)
        ->get(route('admin.contact-messages.index', ['status' => 'spam']))
        ->assertInertia(fn (Assert $page) => $page->has('messages.data', 1));

    $this->actingAs($staff)
        ->get(route('admin.contact-messages.index', ['search' => 'Old']))
        ->assertInertia(fn (Assert $page) => $page->where('messages.data.0.name', 'Older')->has('messages.data', 1));
});

it('moves a message through the workflow and records who did it', function () {
    $message = ContactMessage::factory()->create();
    $staff = staffWith([Permission::ContactMessagesView, Permission::ContactMessagesUpdate]);

    $this->actingAs($staff)->get(route('admin.contact-messages.show', $message))->assertOk();
    $this->actingAs($staff)
        ->put(route('admin.contact-messages.update', $message), ['status' => 'resolved'])
        ->assertRedirect();

    expect($message->fresh())
        ->status->toBe(ContactMessageStatus::Resolved)
        ->handled_by->toBe($staff->id)
        ->handled_at->not->toBeNull();

    $this->assertDatabaseHas('activity_log', ['log_name' => 'contact-messages', 'subject_id' => $message->id, 'event' => 'updated']);

    $this->actingAs($staff)
        ->put(route('admin.contact-messages.update', $message), ['status' => 'archived'])
        ->assertSessionHasErrors('status');
});

it('needs the matching permission for each message action', function () {
    $message = ContactMessage::factory()->create();
    $viewer = staffWith([Permission::ContactMessagesView]);

    $this->actingAs($viewer)->put(route('admin.contact-messages.update', $message), ['status' => 'spam'])->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.contact-messages.destroy', $message))->assertForbidden();

    $this->actingAs(staffWith([Permission::ContactMessagesDelete]))
        ->delete(route('admin.contact-messages.destroy', $message))
        ->assertRedirect(route('admin.contact-messages.index'));

    expect(ContactMessage::count())->toBe(0);
});
