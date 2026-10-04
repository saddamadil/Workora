<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\PortalFileController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrganizationSwitchController;
use App\Http\Controllers\PaymentProfileController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShareLinkController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TimeController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\WorkRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('landing'))->name('home');

// Public: reached by a share link with no sign-in.
Route::prefix('s/{token}')->name('share.')->group(function () {
    Route::get('/', [PublicShareController::class, 'show'])->name('show');
    Route::post('/unlock', [PublicShareController::class, 'unlock'])->middleware('throttle:10,1')->name('unlock');
    Route::get('/preview', [PublicShareController::class, 'preview'])->name('preview');
    Route::get('/download', [PublicShareController::class, 'download'])->name('download');
});

// Invitation links work before sign-in; accepting needs an account.
Route::get('/invite/{token}', [InvitationController::class, 'show'])->name('invite.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    Route::post('/invite/{token}', [InvitationController::class, 'accept'])->name('invite.accept');
    Route::post('/switch/{organization}', OrganizationSwitchController::class)->name('organizations.switch');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // The client portal. A client login is limited to these routes by SetCurrentOrganization.
    Route::prefix('portal')->name('portal.')->group(function () {
        Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/projects', [PortalController::class, 'projects'])->name('projects');
        Route::get('/projects/{project}', [PortalController::class, 'project'])->name('project');
        Route::get('/tasks', [PortalController::class, 'tasks'])->name('tasks');
        Route::get('/invoices', [PortalController::class, 'invoices'])->name('invoices');
        Route::get('/invoices/{invoice}', [PortalController::class, 'invoice'])->name('invoice');
        Route::get('/profile', [PortalController::class, 'profile'])->name('profile');
        Route::post('/profile', [PortalController::class, 'updateProfile'])->name('profile.update');

        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/poll', [MessageController::class, 'poll'])->name('messages.poll');
        Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
        Route::post('/messages/typing', [MessageController::class, 'typing'])->name('messages.typing');
        Route::post('/messages/{message}/important', [MessageController::class, 'important'])->name('messages.important');
        Route::post('/messages/{message}/request', [MessageController::class, 'toRequest'])->name('messages.to-request');

        Route::get('/files', [PortalFileController::class, 'index'])->name('files.index');
        Route::post('/files', [PortalFileController::class, 'store'])->name('files.store');
        Route::get('/files/{file}', [PortalFileController::class, 'show'])->name('files.show');
        Route::get('/files/{file}/download', [PortalFileController::class, 'download'])->name('files.download');

        Route::get('/requests', [RequestController::class, 'portalIndex'])->name('requests.index');
        Route::get('/requests/new', [RequestController::class, 'portalCreate'])->name('requests.create');
        Route::post('/requests', [RequestController::class, 'portalStore'])->name('requests.store');
        Route::get('/requests/{clientRequest}', [RequestController::class, 'portalShow'])->name('requests.show');
    });

    // Staff side of the same features.
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/poll', [MessageController::class, 'poll'])->name('messages.poll');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::post('/messages/typing', [MessageController::class, 'typing'])->name('messages.typing');
    Route::post('/messages/{message}/important', [MessageController::class, 'important'])->name('messages.important');
    Route::post('/messages/{message}/task', [MessageController::class, 'toTask'])->name('messages.to-task');

    Route::post('/projects/{project}/files', [ProjectFileController::class, 'storeForProject'])->name('projects.files.store');
    Route::post('/clients/{client}/files', [ProjectFileController::class, 'storeForClient'])->name('clients.files.store');
    Route::post('/files/{file}/client', [ProjectFileController::class, 'toggleClient'])->name('files.toggle-client');

    Route::post('/projects/{project}/milestones', [MilestoneController::class, 'store'])->name('projects.milestones.store');
    Route::patch('/milestones/{milestone}', [MilestoneController::class, 'update'])->name('milestones.update');
    Route::delete('/milestones/{milestone}', [MilestoneController::class, 'destroy'])->name('milestones.destroy');

    Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/{clientRequest}', [RequestController::class, 'show'])->name('requests.show');
    Route::post('/requests/{clientRequest}/respond', [RequestController::class, 'respond'])->name('requests.respond');
    Route::post('/requests/{clientRequest}/task', [RequestController::class, 'toTask'])->name('requests.to-task');
    Route::post('/requests/{clientRequest}/project', [RequestController::class, 'toProject'])->name('requests.to-project');

    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::get('/team/members', [TeamController::class, 'members'])->name('team.members');
    Route::get('/team/members/{member}', [TeamController::class, 'member'])->name('team.member');
    Route::get('/team/invitations', [TeamController::class, 'invitations'])->name('team.invitations');
    Route::get('/team/roles', [TeamController::class, 'roles'])->name('team.roles');
    Route::get('/team/payment-profiles', [PaymentProfileController::class, 'index'])->name('team.payment-profiles');
    Route::post('/team/payment-profiles', [PaymentProfileController::class, 'store'])->name('team.payment-profiles.store');
    Route::put('/team/payment-profiles/{profile}', [PaymentProfileController::class, 'update'])->name('team.payment-profiles.update');
    Route::post('/team/payment-profiles/{profile}/default', [PaymentProfileController::class, 'makeDefault'])->name('team.payment-profiles.default');
    Route::delete('/team/payment-profiles/{profile}', [PaymentProfileController::class, 'destroy'])->name('team.payment-profiles.destroy');
    Route::post('/team/invite', [TeamController::class, 'invite'])->name('team.invite');
    Route::delete('/team/invitations/{invitation}', [TeamController::class, 'revoke'])->name('team.revoke');
    Route::patch('/team/{member}', [TeamController::class, 'update'])->name('team.update');

    Route::resource('clients', ClientController::class);
    Route::post('/clients/{client}/invite', [ClientController::class, 'invite'])->name('clients.invite');

    Route::resource('projects', ProjectController::class);
    Route::post('/projects/{project}/members', [ProjectController::class, 'addMember'])->name('projects.members.add');
    Route::delete('/projects/{project}/members/{user}', [ProjectController::class, 'removeMember'])->name('projects.members.remove');
    Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('/tasks/{task}/start', [TaskController::class, 'start'])->name('tasks.start');
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::post('/tasks/{task}/submit', [TaskController::class, 'submit'])->name('tasks.submit');
    Route::post('/tasks/{task}/review', [TaskController::class, 'review'])->name('tasks.review');
    Route::post('/tasks/{task}/cancel', [TaskController::class, 'cancel'])->name('tasks.cancel');
    Route::post('/tasks/{task}/reopen', [TaskController::class, 'reopen'])->name('tasks.reopen');
    Route::post('/tasks/{task}/comments', [TaskController::class, 'comment'])->name('tasks.comment');
    Route::post('/tasks/{task}/files', [TaskController::class, 'attach'])->name('tasks.attach');
    Route::post('/tasks/{task}/checklist', [TaskController::class, 'addChecklistItem'])->name('tasks.checklist.add');
    Route::patch('/tasks/{task}/checklist/{item}', [TaskController::class, 'toggleChecklistItem'])->name('tasks.checklist.toggle');
    Route::delete('/tasks/{task}/checklist/{item}', [TaskController::class, 'deleteChecklistItem'])->name('tasks.checklist.delete');

    Route::get('/time', [TimeController::class, 'index'])->name('time.index');
    Route::post('/time/start', [TimeController::class, 'start'])->name('time.start');
    Route::post('/time/stop', [TimeController::class, 'stop'])->name('time.stop');
    Route::post('/time', [TimeController::class, 'store'])->name('time.store');
    Route::post('/time/submit-week', [TimeController::class, 'submitWeek'])->name('time.submit-week');
    Route::delete('/time/{entry}', [TimeController::class, 'destroy'])->name('time.destroy');

    Route::get('/timesheets', [TimesheetController::class, 'index'])->name('timesheets.index');
    Route::get('/timesheets/{timesheet}', [TimesheetController::class, 'show'])->name('timesheets.show');
    Route::post('/timesheets/{timesheet}/approve', [TimesheetController::class, 'approve'])->name('timesheets.approve');
    Route::post('/timesheets/{timesheet}/reject', [TimesheetController::class, 'reject'])->name('timesheets.reject');

    Route::resource('contracts', ContractController::class)->except(['destroy']);
    Route::post('/contracts/{contract}/send', [ContractController::class, 'send'])->name('contracts.send');
    Route::post('/contracts/{contract}/accept', [ContractController::class, 'accept'])->name('contracts.accept');
    Route::post('/contracts/{contract}/decline', [ContractController::class, 'decline'])->name('contracts.decline');
    Route::post('/contracts/{contract}/terminate', [ContractController::class, 'terminate'])->name('contracts.terminate');
    Route::post('/contracts/{contract}/milestones', [ContractController::class, 'addMilestone'])->name('contracts.milestones.add');
    Route::delete('/contracts/{contract}/milestones/{milestone}', [ContractController::class, 'deleteMilestone'])->name('contracts.milestones.delete');
    Route::post('/contracts/{contract}/milestones/{milestone}/submit', [ContractController::class, 'submitMilestone'])->name('contracts.milestones.submit');
    Route::post('/contracts/{contract}/milestones/{milestone}/approve', [ContractController::class, 'approveMilestone'])->name('contracts.milestones.approve');
    Route::post('/contracts/{contract}/milestones/{milestone}/reopen', [ContractController::class, 'reopenMilestone'])->name('contracts.milestones.reopen');

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/team/invoices', [InvoiceController::class, 'index'])->name('team.invoices');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::post('/invoices/{invoice}/items', [InvoiceController::class, 'addItem'])->name('invoices.items.add');
    Route::delete('/invoices/{invoice}/items/{item}', [InvoiceController::class, 'removeItem'])->name('invoices.items.remove');
    Route::post('/invoices/{invoice}/import-time', [InvoiceController::class, 'importTime'])->name('invoices.import-time');
    Route::post('/invoices/{invoice}/import-milestones', [InvoiceController::class, 'importMilestones'])->name('invoices.import-milestones');
    Route::post('/invoices/{invoice}/payment-profile', [InvoiceController::class, 'paymentProfile'])->name('invoices.payment-profile');
    Route::post('/invoices/{invoice}/template', [InvoiceController::class, 'template'])->name('invoices.template');
    Route::get('/invoices/{invoice}/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'preview'])->name('invoices.print');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::post('/invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('/invoices/{invoice}/approve', [InvoiceController::class, 'approve'])->name('invoices.approve');
    Route::post('/invoices/{invoice}/reject', [InvoiceController::class, 'reject'])->name('invoices.reject');
    Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.payments.store');
    Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');

    Route::get('/assets/avatar/{user}', [AssetController::class, 'avatar'])->name('assets.avatar');
    Route::get('/assets/company-logo', [AssetController::class, 'companyLogo'])->name('assets.company-logo');
    Route::get('/assets/client-logo/{client}', [AssetController::class, 'clientLogo'])->name('assets.client-logo');
    Route::get('/profile/signature', [AssetController::class, 'signature'])->name('profile.signature');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::get('/settings/company', [SettingsController::class, 'company'])->name('settings.company');
    Route::post('/settings/company', [SettingsController::class, 'updateCompany'])->name('settings.company.update');

    Route::get('/work-requests', [WorkRequestController::class, 'index'])->name('work-requests.index');
    Route::get('/work-requests/create', [WorkRequestController::class, 'create'])->name('work-requests.create');
    Route::post('/work-requests', [WorkRequestController::class, 'store'])->name('work-requests.store');
    Route::get('/work-requests/{workRequest}', [WorkRequestController::class, 'show'])->name('work-requests.show');
    Route::post('/work-requests/{workRequest}/messages', [WorkRequestController::class, 'message'])->name('work-requests.message');
    Route::post('/work-requests/{workRequest}/approve', [WorkRequestController::class, 'approve'])->name('work-requests.approve');
    Route::post('/work-requests/{workRequest}/reject', [WorkRequestController::class, 'reject'])->name('work-requests.reject');
    Route::post('/work-requests/{workRequest}/withdraw', [WorkRequestController::class, 'withdraw'])->name('work-requests.withdraw');

    Route::get('/files', [FileController::class, 'index'])->name('files.index');
    Route::post('/files', [FileController::class, 'store'])->name('files.store');
    Route::get('/files/{file}', [FileController::class, 'show'])->name('files.show');
    Route::get('/files/{file}/download', [FileController::class, 'download'])->name('files.download');
    Route::patch('/files/{file}', [FileController::class, 'update'])->name('files.update');
    Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('files.destroy');
    Route::post('/files/{file}/share', [FileController::class, 'share'])->name('files.share');

    Route::get('/shared', [ShareLinkController::class, 'index'])->name('shares.index');
    Route::delete('/shared/{link}', [ShareLinkController::class, 'destroy'])->name('shares.destroy');

});
