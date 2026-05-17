<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\EventExportController;
use App\Http\Controllers\EventMemberController;
use App\Http\Controllers\EventRuleController;
use App\Http\Controllers\EventTypeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\OrganizationUserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\TeamDraftController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return redirect()->route('organizations.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');

    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');

    Route::post('/organizations/{organization}/settings', [OrganizationSettingsController::class, 'update'])->name('organizations.settings.update');

    Route::get('/organizations/{organization}/users', [OrganizationUserController::class, 'index'])->name('organizations.users.index');
    Route::patch('/organizations/{organization}/users/{user}', [OrganizationUserController::class, 'update'])->name('organizations.users.update');
    Route::delete('/organizations/{organization}/users/{user}', [OrganizationUserController::class, 'destroy'])->name('organizations.users.destroy');

    // Invitations (org-scoped, admin only)
    Route::post('/organizations/{organization}/invitations', [InvitationController::class, 'store'])->name('organizations.invitations.store');
    Route::delete('/organizations/{organization}/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('organizations.invitations.destroy');

    Route::get('/organizations/{organization}/members', [MemberController::class, 'index'])->name('organizations.members.index');
    Route::post('/organizations/{organization}/members', [MemberController::class, 'store'])->name('organizations.members.store');
    Route::post('/organizations/{organization}/members/import', [MemberController::class, 'import'])->name('organizations.members.import');
    Route::patch('/organizations/{organization}/members/{member}', [MemberController::class, 'update'])->name('organizations.members.update');
    Route::delete('/organizations/{organization}/members/{member}', [MemberController::class, 'destroy'])->name('organizations.members.destroy');
    Route::post('/organizations/{organization}/members/{member}/restore', [MemberController::class, 'restore'])->name('organizations.members.restore');

    Route::scopeBindings()->group(function () {
        Route::get('/organizations/{organization}/event-types', [EventTypeController::class, 'index'])->name('organizations.event-types.index');
        Route::post('/organizations/{organization}/event-types', [EventTypeController::class, 'store'])->name('organizations.event-types.store');
        Route::patch('/organizations/{organization}/event-types/{eventType}', [EventTypeController::class, 'update'])->name('organizations.event-types.update');
        Route::delete('/organizations/{organization}/event-types/{eventType}', [EventTypeController::class, 'destroy'])->name('organizations.event-types.destroy');

        Route::get('/organizations/{organization}/sectors', [SectorController::class, 'index'])->name('organizations.sectors.index');
        Route::post('/organizations/{organization}/sectors', [SectorController::class, 'store'])->name('organizations.sectors.store');

        Route::get('/organizations/{organization}/events', [EventController::class, 'index'])->name('organizations.events.index');
        Route::post('/organizations/{organization}/events', [EventController::class, 'store'])->name('organizations.events.store');
        Route::get('/organizations/{organization}/events/{event}', [EventController::class, 'show'])->name('organizations.events.show');
        Route::patch('/organizations/{organization}/events/{event}', [EventController::class, 'update'])->name('organizations.events.update');
        Route::delete('/organizations/{organization}/events/{event}', [EventController::class, 'destroy'])->name('organizations.events.destroy');
        Route::post('/organizations/{organization}/events/{event}/duplicate', [EventController::class, 'duplicate'])->name('organizations.events.duplicate');
        Route::post('/organizations/{organization}/events/{event}/revert-final', [TeamDraftController::class, 'revertFinal'])->name('organizations.events.revert-final');

        Route::get('/organizations/{organization}/events/{event}/export/print', [EventExportController::class, 'print'])->name('organizations.events.export.print');
        Route::get('/organizations/{organization}/events/{event}/export.csv', [EventExportController::class, 'exportCsv'])->name('organizations.events.export.csv');
        Route::get('/organizations/{organization}/events/{event}/export.xlsx', [EventExportController::class, 'exportXlsx'])->name('organizations.events.export.xlsx');

        Route::get('/organizations/{organization}/events/{event}/members', [EventMemberController::class, 'index'])->name('organizations.events.members.index');
        Route::post('/organizations/{organization}/events/{event}/members', [EventMemberController::class, 'store'])->name('organizations.events.members.store');
        Route::post('/organizations/{organization}/events/{event}/members/copy-from-event', [EventMemberController::class, 'copyFromEvent'])->name('organizations.events.members.copy-from-event');
        Route::patch('/organizations/{organization}/events/{event}/members/bulk', [EventMemberController::class, 'bulkUpdate'])->name('organizations.events.members.bulk-update');
        Route::patch('/organizations/{organization}/events/{event}/members/{eventMember}', [EventMemberController::class, 'update'])->name('organizations.events.members.update');
        Route::delete('/organizations/{organization}/events/{event}/members/{eventMember}', [EventMemberController::class, 'destroy'])->name('organizations.events.members.destroy');

        Route::post('/organizations/{organization}/events/{event}/rules', [EventRuleController::class, 'store'])->name('organizations.events.rules.store');
        Route::patch('/organizations/{organization}/events/{event}/rules/{rule}', [EventRuleController::class, 'update'])->name('organizations.events.rules.update');
        Route::delete('/organizations/{organization}/events/{event}/rules/{rule}', [EventRuleController::class, 'destroy'])->name('organizations.events.rules.destroy');

        Route::get('/organizations/{organization}/events/{event}/team-drafts/conflicts', [TeamDraftController::class, 'conflicts'])->name('organizations.events.team-drafts.conflicts');
        Route::post('/organizations/{organization}/events/{event}/team-drafts/generate', [TeamDraftController::class, 'generate'])->name('organizations.events.team-drafts.generate');
        Route::get('/organizations/{organization}/events/{event}/team-drafts/{teamDraft}', [TeamDraftController::class, 'show'])->name('organizations.events.team-drafts.show');
        Route::delete('/organizations/{organization}/events/{event}/team-drafts/{teamDraft}', [TeamDraftController::class, 'destroy'])->name('organizations.events.team-drafts.destroy');
        Route::post('/organizations/{organization}/events/{event}/team-drafts/{teamDraft}/finalize', [TeamDraftController::class, 'finalize'])->name('organizations.events.team-drafts.finalize');
        Route::patch('/organizations/{organization}/events/{event}/team-drafts/{teamDraft}/names', [TeamDraftController::class, 'updateNames'])->name('organizations.events.team-drafts.update-names');
        Route::patch('/organizations/{organization}/events/{event}/team-drafts/{teamDraft}/team-members', [TeamDraftController::class, 'updateTeamMembers'])->name('organizations.events.team-drafts.update-team-members');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Invitation accept — accessible by guests and authenticated users
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');

require __DIR__.'/auth.php';
