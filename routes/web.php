<?php

use App\Http\Controllers\ApprovedSelectionController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationRuleController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\OrganizationUserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SelectionDraftController;
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
    Route::post('/organizations/{organization}/users', [OrganizationUserController::class, 'store'])->name('organizations.users.store');
    Route::patch('/organizations/{organization}/users/{user}', [OrganizationUserController::class, 'update'])->name('organizations.users.update');
    Route::delete('/organizations/{organization}/users/{user}', [OrganizationUserController::class, 'destroy'])->name('organizations.users.destroy');

    Route::get('/organizations/{organization}/members', [MemberController::class, 'index'])->name('organizations.members.index');
    Route::post('/organizations/{organization}/members', [MemberController::class, 'store'])->name('organizations.members.store');
    Route::post('/organizations/{organization}/members/import', [MemberController::class, 'import'])->name('organizations.members.import');
    Route::patch('/organizations/{organization}/members/{member}', [MemberController::class, 'update'])->name('organizations.members.update');
    Route::delete('/organizations/{organization}/members/{member}', [MemberController::class, 'destroy'])->name('organizations.members.destroy');
    Route::post('/organizations/{organization}/members/{member}/restore', [MemberController::class, 'restore'])->name('organizations.members.restore');

    Route::get('/organizations/{organization}/rules', [OrganizationRuleController::class, 'index'])->name('organizations.rules.index');
    Route::post('/organizations/{organization}/rules', [OrganizationRuleController::class, 'store'])->name('organizations.rules.store');
    Route::patch('/organizations/{organization}/rules/{rule}', [OrganizationRuleController::class, 'update'])->name('organizations.rules.update');
    Route::delete('/organizations/{organization}/rules/{rule}', [OrganizationRuleController::class, 'destroy'])->name('organizations.rules.destroy');

    Route::get('/organizations/{organization}/drafts', [SelectionDraftController::class, 'index'])->name('organizations.drafts.index');
    Route::post('/organizations/{organization}/drafts', [SelectionDraftController::class, 'store'])->name('organizations.drafts.store');
    Route::get('/organizations/{organization}/drafts/{draft}', [SelectionDraftController::class, 'show'])->name('organizations.drafts.show');
    Route::post('/organizations/{organization}/drafts/{draft}/generate', [SelectionDraftController::class, 'generate'])->name('organizations.drafts.generate');
    Route::post('/organizations/{organization}/drafts/{draft}/approve', [SelectionDraftController::class, 'approve'])->name('organizations.drafts.approve');
    Route::delete('/organizations/{organization}/drafts/{draft}', [SelectionDraftController::class, 'destroy'])->name('organizations.drafts.destroy');

    Route::get('/organizations/{organization}/selections', [ApprovedSelectionController::class, 'index'])->name('organizations.selections.index');
    Route::get('/organizations/{organization}/selections/{selection}', [ApprovedSelectionController::class, 'show'])->name('organizations.selections.show');
    Route::get('/organizations/{organization}/selections/{selection}/print', [ApprovedSelectionController::class, 'print'])->name('organizations.selections.print');
    Route::get('/organizations/{organization}/selections/{selection}/export/csv', [ApprovedSelectionController::class, 'exportCsv'])->name('organizations.selections.export.csv');
    Route::get('/organizations/{organization}/selections/{selection}/export/xlsx', [ApprovedSelectionController::class, 'exportXlsx'])->name('organizations.selections.export.xlsx');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
