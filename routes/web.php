<?php

use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\PresentationModalityController;
use App\Http\Controllers\Admin\ProcedureCategoryController;
use App\Http\Controllers\Admin\ProcedureDynamicFieldController;
use App\Http\Controllers\Admin\ProcedurePrerequisiteController;
use App\Http\Controllers\Admin\ProcedureRequirementController;
use App\Http\Controllers\Admin\ProcedureTypeController;
use App\Http\Controllers\Admin\ProcedureVariantController;
use App\Http\Controllers\Admin\StatusController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Applicant\CorrectionController;
use App\Http\Controllers\Applicant\ProcedureRequestController;
use App\Http\Controllers\ApplicationWorkController;
use App\Http\Controllers\AreaManager\AssignmentController;
use App\Http\Controllers\AreaManager\AttentionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FrontDesk\ClosureController;
use App\Http\Controllers\FrontDesk\DerivationController;
use App\Http\Controllers\FrontDesk\PhysicalProcedureRegistrationController;
use App\Http\Controllers\FrontDesk\ReviewController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProcedureCommunicationController;
use App\Http\Controllers\ProcedureHistoryController;
use App\Http\Controllers\ProcedureRequestSearchController;
use App\Http\Controllers\ProfessionalExamController;
use App\Http\Controllers\PublicProcedureCatalogController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TitleFinalDossierController;
use App\Http\Controllers\TitleProcessController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/tramites', [PublicProcedureCatalogController::class, 'index'])->name('catalog.index');
Route::get('/tramites/{procedureType:code}', [PublicProcedureCatalogController::class, 'show'])->name('catalog.show');
Route::post('/tramites/{procedureType:code}/seleccionar-variante', [PublicProcedureCatalogController::class, 'select'])->name('catalog.select');
Route::post('/tramites/{procedureType:code}/iniciar', [PublicProcedureCatalogController::class, 'start'])->name('catalog.start');
Route::get('/tramites/{procedureType:code}/continuar', [PublicProcedureCatalogController::class, 'resume'])->middleware(['auth', 'active'])->name('catalog.resume');

Route::get('/comprobacion', HealthCheckController::class)->middleware('throttle:30,1')->name('check');

Route::middleware('guest')->group(function () {
    Route::get('/registro', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/registro', [RegisteredUserController::class, 'store']);

    Route::get('/iniciar-sesion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/iniciar-sesion', [AuthenticatedSessionController::class, 'store']);

    Route::get('/recuperar-contrasena', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/recuperar-contrasena', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::get('/restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/restablecer-contrasena', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/cerrar-sesion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/panel', [DashboardController::class, 'redirect'])->name('dashboard');
    Route::get('/notificaciones', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notificaciones/leer-todas', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('/notificaciones/{notification}/leer', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notificaciones/{notification}/abrir', [NotificationController::class, 'open'])->name('notifications.open');
    Route::get('/consulta-expedientes', [ProcedureRequestSearchController::class, 'index'])->name('search.index');
    Route::get('/consulta-expedientes-exportar', [ProcedureRequestSearchController::class, 'export'])->middleware('throttle:10,1')->name('search.export');
    Route::get('/consulta-expedientes/{procedureRequest}', [ProcedureRequestSearchController::class, 'show'])->name('search.show');
    Route::get('/reportes', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/expedientes/{procedureRequest}/historial', [ProcedureHistoryController::class, 'show'])->name('history.staff');
    Route::prefix('/expedientes/{procedureRequest}/comunicaciones')->name('communications.')->group(function () {
        Route::get('/', [ProcedureCommunicationController::class, 'show'])->name('show');
        Route::post('/notificaciones', [ProcedureCommunicationController::class, 'notify'])->name('notify');
        Route::post('/citas', [ProcedureCommunicationController::class, 'schedule'])->name('appointments.store');
        Route::post('/citas/{appointment}/reprogramar', [ProcedureCommunicationController::class, 'reschedule'])->name('appointments.reschedule');
        Route::patch('/citas/{appointment}/estado', [ProcedureCommunicationController::class, 'appointmentStatus'])->name('appointments.status');
        Route::post('/recojo', [ProcedureCommunicationController::class, 'ready'])->name('pickup.ready');
        Route::post('/recojo/entregar', [ProcedureCommunicationController::class, 'deliver'])->name('pickup.deliver');
    });

    Route::get('/panel/solicitante', [DashboardController::class, 'applicant'])
        ->middleware('role:solicitante')
        ->name('dashboard.applicant');

    Route::prefix('/panel/solicitante/solicitudes')->name('applicant.procedure-requests.')
        ->middleware('role:solicitante')->group(function () {
            Route::get('/', [ProcedureRequestController::class, 'index'])->name('index');
            Route::get('/nueva', [ProcedureRequestController::class, 'create'])->name('create');
            Route::post('/', [ProcedureRequestController::class, 'store'])->name('store');
            Route::get('/{procedureRequest}', [ProcedureRequestController::class, 'show'])->name('show');
            Route::get('/{procedureRequest}/documentos/{document}/descargar', [ProcedureRequestController::class, 'download'])
                ->middleware('throttle:30,1')->name('documents.download');
            Route::get('/{procedureRequest}/respuesta/descargar', [ProcedureRequestController::class, 'downloadResponse'])
                ->name('response.download');
            Route::get('/{procedureRequest}/subsanar', [CorrectionController::class, 'create'])->name('corrections.create');
            Route::post('/{procedureRequest}/subsanar', [CorrectionController::class, 'store'])->name('corrections.store');
        });
    Route::get('/panel/mesa-de-partes', [DashboardController::class, 'frontDesk'])
        ->middleware('role:mesa-partes')
        ->name('dashboard.front-desk');
    Route::get('/panel/mesa-de-partes/registro-presencial', [PhysicalProcedureRegistrationController::class, 'create'])->name('front-desk.physical-registration.create');
    Route::post('/panel/mesa-de-partes/registro-presencial', [PhysicalProcedureRegistrationController::class, 'store'])->name('front-desk.physical-registration.store');
    Route::get('/panel/mesa-de-partes/registro-presencial/{procedureRequest}/constancia', [PhysicalProcedureRegistrationController::class, 'receipt'])->name('front-desk.physical-registration.receipt');
    Route::prefix('/panel/mesa-de-partes/revision')->name('front-desk.reviews.')
        ->middleware('role:mesa-partes')->group(function () {
            Route::get('/', [ReviewController::class, 'index'])->name('index');
            Route::get('/{procedureRequest}', [ReviewController::class, 'show'])->name('show');
            Route::get('/{procedureRequest}/documentos/{document}/descargar', [ReviewController::class, 'download'])->middleware('throttle:30,1')->name('documents.download');
            Route::patch('/{procedureRequest}/iniciar', [ReviewController::class, 'start'])->name('start');
            Route::patch('/{procedureRequest}/validar', [ReviewController::class, 'validateRequest'])->name('validate');
            Route::post('/{procedureRequest}/recepcion-fisica', [ReviewController::class, 'confirmPhysicalReception'])->name('physical-reception.confirm');
            Route::post('/{procedureRequest}/expediente-academico', [ReviewController::class, 'assignAcademicFileNumber'])->name('academic-file.assign');
            Route::post('/{procedureRequest}/observar', [ReviewController::class, 'observe'])->name('observe');
            Route::post('/{procedureRequest}/rechazar', [ReviewController::class, 'reject'])->name('reject');
        });
    Route::prefix('/panel/mesa-de-partes/derivaciones')->name('front-desk.derivations.')
        ->middleware('role:mesa-partes')->group(function () {
            Route::get('/', [DerivationController::class, 'index'])->name('index');
            Route::get('/{procedureRequest}', [DerivationController::class, 'create'])->name('create');
            Route::post('/{procedureRequest}', [DerivationController::class, 'store'])->name('store');
        });
    Route::prefix('/panel/mesa-de-partes/cierres')->name('front-desk.closures.')
        ->middleware('role:mesa-partes')->group(function () {
            Route::get('/', [ClosureController::class, 'index'])->name('index');
            Route::get('/{procedureRequest}', [ClosureController::class, 'show'])->name('show');
            Route::get('/{procedureRequest}/respuesta/descargar', [ClosureController::class, 'downloadResponse'])->name('response.download');
            Route::patch('/{procedureRequest}/finalizar', [ClosureController::class, 'finalize'])->name('finalize');
        });
    Route::get('/panel/responsable-de-area', [DashboardController::class, 'areaManager'])
        ->middleware('role:responsable-area')
        ->name('dashboard.area-manager');
    Route::prefix('/panel/responsable-de-area/expedientes')->name('area-manager.assignments.')
        ->middleware('role:responsable-area')->group(function () {
            Route::get('/', [AssignmentController::class, 'index'])->name('index');
            Route::get('/{procedureRequest}', [AssignmentController::class, 'show'])->name('show');
            Route::get('/{procedureRequest}/documentos/{document}/descargar', [AssignmentController::class, 'download'])->middleware('throttle:30,1')->name('documents.download');
            Route::patch('/{procedureRequest}/derivaciones/{derivation}/recibir', [AssignmentController::class, 'receive'])->name('receive');
            Route::patch('/{procedureRequest}/iniciar-atencion', [AttentionController::class, 'start'])->name('attention.start');
            Route::post('/{procedureRequest}/acciones', [AttentionController::class, 'storeAction'])->name('attention-actions.store');
            Route::post('/{procedureRequest}/respuesta', [AttentionController::class, 'storeResponse'])->name('response.store');
            Route::get('/{procedureRequest}/respuesta/descargar', [AttentionController::class, 'downloadResponse'])->name('response.download');
        });
    Route::get('/panel/administracion', [DashboardController::class, 'administrator'])
        ->middleware('role:administrador')
        ->name('dashboard.administrator');

    Route::prefix('/panel/administracion/usuarios')->name('admin.users.')
        ->middleware('role:administrador')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::get('/crear', [UserController::class, 'create'])->name('create');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::get('/{user}', [UserController::class, 'show'])->name('show');
            Route::get('/{user}/editar', [UserController::class, 'edit'])->name('edit');
            Route::put('/{user}', [UserController::class, 'update'])->name('update');
            Route::patch('/{user}/estado', [UserController::class, 'toggle'])->name('toggle');
            Route::post('/{user}/restablecer-acceso', [UserController::class, 'resetAccess'])->name('reset-access');
        });
    Route::get('/panel/administracion/auditoria', [AuditLogController::class, 'index'])
        ->middleware('role:administrador')->name('admin.audit-logs.index');

    Route::prefix('/panel/titulacion/{titleProcess}')->name('title-processes.')->group(function () {
        Route::get('/', [TitleProcessController::class, 'show'])->name('show');
        Route::post('/verificar-elegibilidad', [TitleProcessController::class, 'verify'])->name('verify');
        Route::post('/etapa', [TitleProcessController::class, 'transition'])->name('transition');
        Route::post('/programacion', [TitleProcessController::class, 'schedule'])->name('schedule');
        Route::post('/programacion/{schedule}/reprogramar', [TitleProcessController::class, 'reschedule'])->name('reschedule');
        Route::post('/resultado', [TitleProcessController::class, 'result'])->name('result');
        Route::post('/trabajo-aplicacion/propuesta', [ApplicationWorkController::class, 'store'])->name('application-work.store');
        Route::post('/trabajo-aplicacion/{project}/aprobacion', [ApplicationWorkController::class, 'approve'])->name('application-work.approve');
        Route::post('/trabajo-aplicacion/{project}/requisito', [ApplicationWorkController::class, 'requirement'])->name('application-work.requirement');
        Route::post('/trabajo-aplicacion/{project}/originalidad', [ApplicationWorkController::class, 'originality'])->name('application-work.originality');
        Route::post('/examen-suficiencia', [ProfessionalExamController::class, 'store'])->name('professional-exam.store');
        Route::post('/examen-suficiencia/{profile}/requisito', [ProfessionalExamController::class, 'requirement'])->name('professional-exam.requirement');
        Route::post('/examen-suficiencia/{profile}/programacion', [ProfessionalExamController::class, 'schedule'])->name('professional-exam.schedule');
        Route::post('/examen-suficiencia/{profile}/programacion/{schedule}/reprogramar', [ProfessionalExamController::class, 'reschedule'])->name('professional-exam.reschedule');
        Route::post('/examen-suficiencia/intentos/{attempt}/resultado', [ProfessionalExamController::class, 'result'])->name('professional-exam.result');
        Route::post('/expediente-final', [TitleFinalDossierController::class, 'store'])->name('final-dossier.store');
        Route::post('/expediente-final/{dossier}/requisitos/{requirement}', [TitleFinalDossierController::class, 'requirement'])->name('final-dossier.requirement');
        Route::post('/expediente-final/{dossier}/accion', [TitleFinalDossierController::class, 'action'])->name('final-dossier.action');
    });

    Route::prefix('/panel/administracion/catalogos')->name('admin.')
        ->middleware('role:administrador')->group(function () {
            Route::resource('/categorias-tramite', ProcedureCategoryController::class)->except(['show', 'destroy'])->parameters(['categorias-tramite' => 'procedureCategory'])->names('procedure-categories');
            Route::patch('/categorias-tramite/{procedureCategory}/estado', [ProcedureCategoryController::class, 'toggle'])->name('procedure-categories.toggle');
            Route::resource('/modalidades', PresentationModalityController::class)->except(['show', 'destroy'])->parameters(['modalidades' => 'presentationModality'])->names('presentation-modalities');
            Route::patch('/modalidades/{presentationModality}/estado', [PresentationModalityController::class, 'toggle'])->name('presentation-modalities.toggle');
            Route::get('/areas', [AreaController::class, 'index'])->name('areas.index');
            Route::get('/areas/crear', [AreaController::class, 'create'])->name('areas.create');
            Route::post('/areas', [AreaController::class, 'store'])->name('areas.store');
            Route::get('/areas/{area}/editar', [AreaController::class, 'edit'])->name('areas.edit');
            Route::put('/areas/{area}', [AreaController::class, 'update'])->name('areas.update');
            Route::patch('/areas/{area}/estado', [AreaController::class, 'toggle'])->name('areas.toggle');

            Route::get('/tipos-tramite', [ProcedureTypeController::class, 'index'])->name('procedure-types.index');
            Route::get('/tipos-tramite/crear', [ProcedureTypeController::class, 'create'])->name('procedure-types.create');
            Route::post('/tipos-tramite', [ProcedureTypeController::class, 'store'])->name('procedure-types.store');
            Route::get('/tipos-tramite/{procedureType}/editar', [ProcedureTypeController::class, 'edit'])->name('procedure-types.edit');
            Route::put('/tipos-tramite/{procedureType}', [ProcedureTypeController::class, 'update'])->name('procedure-types.update');
            Route::patch('/tipos-tramite/{procedureType}/estado', [ProcedureTypeController::class, 'toggle'])->name('procedure-types.toggle');

            Route::resource('/tipos-tramite/{procedureType}/variantes', ProcedureVariantController::class)->except(['show', 'destroy'])->parameters(['variantes' => 'procedureVariant'])->names('procedure-types.variants');
            Route::patch('/tipos-tramite/{procedureType}/variantes/{procedureVariant}/estado', [ProcedureVariantController::class, 'toggle'])->name('procedure-types.variants.toggle');
            Route::resource('/tipos-tramite/{procedureType}/campos', ProcedureDynamicFieldController::class)->except(['show', 'destroy'])->parameters(['campos' => 'dynamicField'])->names('procedure-types.dynamic-fields');
            Route::patch('/tipos-tramite/{procedureType}/campos/{dynamicField}/estado', [ProcedureDynamicFieldController::class, 'toggle'])->name('procedure-types.dynamic-fields.toggle');
            Route::resource('/tipos-tramite/{procedureType}/prerrequisitos', ProcedurePrerequisiteController::class)->except(['show', 'destroy'])->parameters(['prerrequisitos' => 'prerequisite'])->names('procedure-types.prerequisites');
            Route::patch('/tipos-tramite/{procedureType}/prerrequisitos/{prerequisite}/estado', [ProcedurePrerequisiteController::class, 'toggle'])->name('procedure-types.prerequisites.toggle');

            Route::get('/tipos-tramite/{procedureType}/requisitos', [ProcedureRequirementController::class, 'index'])->name('procedure-types.requirements.index');
            Route::get('/tipos-tramite/{procedureType}/requisitos/crear', [ProcedureRequirementController::class, 'create'])->name('procedure-types.requirements.create');
            Route::post('/tipos-tramite/{procedureType}/requisitos', [ProcedureRequirementController::class, 'store'])->name('procedure-types.requirements.store');
            Route::get('/tipos-tramite/{procedureType}/requisitos/{requirement}/editar', [ProcedureRequirementController::class, 'edit'])->name('procedure-types.requirements.edit');
            Route::put('/tipos-tramite/{procedureType}/requisitos/{requirement}', [ProcedureRequirementController::class, 'update'])->name('procedure-types.requirements.update');
            Route::patch('/tipos-tramite/{procedureType}/requisitos/{requirement}/estado', [ProcedureRequirementController::class, 'toggle'])->name('procedure-types.requirements.toggle');

            Route::get('/estados', [StatusController::class, 'index'])->name('statuses.index');
        });
});
