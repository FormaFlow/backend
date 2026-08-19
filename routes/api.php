<?php

declare(strict_types=1);

use FormaFlow\Entries\Infrastructure\Http\EntryController;
use FormaFlow\Entries\Infrastructure\Http\EntryShareController;
use FormaFlow\Entries\Infrastructure\Http\PublicApiEntryController;
use FormaFlow\Forms\Infrastructure\Http\FormController;
use FormaFlow\Forms\Infrastructure\Http\PublicApiFormController;
use FormaFlow\Identity\Infrastructure\Http\AuthController;
use FormaFlow\Reports\Infrastructure\Http\DashboardController;
use FormaFlow\Reports\Infrastructure\Http\ReportController;
use FormaFlow\Reminders\Infrastructure\Http\PushSubscriptionController;
use FormaFlow\Reminders\Infrastructure\Http\QuizAssignmentController;
use FormaFlow\Reminders\Infrastructure\Http\UserSearchController;
use FormaFlow\Payments\Infrastructure\Http\PaymentController;
use FormaFlow\Learning\Infrastructure\Http\LearningAssessmentController;
use FormaFlow\Learning\Infrastructure\Http\LearningCycleController;
use FormaFlow\Learning\Infrastructure\Http\LearningProgressController;
use FormaFlow\Learning\Infrastructure\Http\StudyScheduleController;
use FormaFlow\Learning\Infrastructure\Http\TutorController;
use FormaFlow\Learning\Infrastructure\Http\LearningMediaController;
use FormaFlow\Workspaces\Infrastructure\Http\ManagedLearnerController;
use FormaFlow\Workspaces\Infrastructure\Http\WorkspaceController;
use FormaFlow\Workspaces\Infrastructure\Http\WorkspaceInvitationController;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

Route::options('{any}', static function () {
    return response('', Response::HTTP_OK);
})->where('any', '.*');

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/managed-login', [AuthController::class, 'managedLogin']);

    // Public access for shared results
    Route::get('/public/entries/{id}', [PublicApiEntryController::class, 'show']);
    Route::get('/public/forms/{id}', [PublicApiFormController::class, 'show']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::patch('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/workspaces', [WorkspaceController::class, 'index']);
        Route::patch('/workspaces/{workspace}/modules/{module}', [WorkspaceController::class, 'updateModule']);
        Route::get('/workspaces/{workspace}/learners', [ManagedLearnerController::class, 'index']);
        Route::post('/workspaces/{workspace}/learners', [ManagedLearnerController::class, 'store']);
        Route::get('/workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'index']);
        Route::post('/workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'store']);
        Route::post('/workspaces/invitations/accept', [WorkspaceInvitationController::class, 'accept']);
        Route::post('/workspaces/{workspace}/learning/import/preview', [LearningAssessmentController::class, 'preview']);
        Route::post('/workspaces/{workspace}/learning/import', [LearningAssessmentController::class, 'import']);
        Route::post('/workspaces/{workspace}/learning/media', [LearningMediaController::class, 'store']);
        Route::get('/workspaces/{workspace}/learning/library', [LearningAssessmentController::class, 'library']);
        Route::post('/workspaces/{workspace}/learning/library/{pack}/install', [LearningAssessmentController::class, 'installBuiltIn']);
        Route::get('/workspaces/{workspace}/learning/assessments', [LearningAssessmentController::class, 'index']);
        Route::get('/workspaces/{workspace}/learning/assessments/{assessment}/editor', [LearningAssessmentController::class, 'editor']);
        Route::get('/workspaces/{workspace}/learning/assessments/{assessment}/versions/current', [LearningAssessmentController::class, 'currentVersion']);
        Route::patch('/workspaces/{workspace}/learning/assessments/{assessment}/questions/{field}', [LearningAssessmentController::class, 'updateQuestion']);
        Route::post('/workspaces/{workspace}/learning/assessments/{assessment}/publish', [LearningAssessmentController::class, 'publish']);
        Route::post('/workspaces/{workspace}/learning/assignments', [LearningCycleController::class, 'createAssignment']);
        Route::patch('/workspaces/{workspace}/learning/assignments/{assignment}', [LearningCycleController::class, 'updateAssignment']);
        Route::delete('/workspaces/{workspace}/learning/assignments/{assignment}', [LearningCycleController::class, 'deleteAssignment']);
        Route::post('/workspaces/{workspace}/learning/assignments/{assignment}/reopen', [LearningCycleController::class, 'reopenAssignment']);
        Route::post('/workspaces/{workspace}/learning/assignments/{assignment}/attempts', [LearningCycleController::class, 'startAttempt']);
        Route::post('/workspaces/{workspace}/learning/attempts/{attempt}/submit', [LearningCycleController::class, 'submit']);
        Route::get('/workspaces/{workspace}/learning/today', [LearningCycleController::class, 'today']);
        Route::get('/workspaces/{workspace}/learning/reviews/due', [LearningCycleController::class, 'dueReviews']);
        Route::post('/workspaces/{workspace}/learning/reviews/{review}/answer', [LearningCycleController::class, 'answerReview']);
        Route::get('/workspaces/{workspace}/learning/progress', [LearningProgressController::class, 'index']);
        Route::get('/workspaces/{workspace}/learning/progress/{learner}', [LearningProgressController::class, 'timeline']);
        Route::get('/workspaces/{workspace}/learning/schedules/{learner}', [StudyScheduleController::class, 'show']);
        Route::put('/workspaces/{workspace}/learning/schedules/{learner}', [StudyScheduleController::class, 'upsert']);
        Route::post('/workspaces/{workspace}/learning/tutor/explain', [TutorController::class, 'explain']);
        Route::get('/users/search', UserSearchController::class);
        Route::get('/push/config', [PushSubscriptionController::class, 'config']);
        Route::post('/push/subscriptions', [PushSubscriptionController::class, 'store']);
        Route::delete('/push/subscriptions', [PushSubscriptionController::class, 'destroy']);
        Route::get('/quizzes', [QuizAssignmentController::class, 'library']);

        Route::prefix('forms')->group(function () {
            Route::get('/', [FormController::class, 'index']);
            Route::post('/', [FormController::class, 'store']);
            Route::get('{id}', [FormController::class, 'show']);
            Route::patch('{id}', [FormController::class, 'update']);
            Route::delete('{id}', [FormController::class, 'destroy']);
            Route::delete('{formId}/fields/{fieldId}', [FormController::class, 'removeField']);
            Route::patch('{formId}/fields/{fieldId}', [FormController::class, 'updateField']);
            Route::post('{id}/publish', [FormController::class, 'publish']);
            Route::post('{id}/fields', [FormController::class, 'addField']);
            Route::post('{id}/entries/import', [FormController::class, 'importEntries']);
            Route::get('{id}/assignments', [QuizAssignmentController::class, 'index']);
            Route::post('{id}/assignments', [QuizAssignmentController::class, 'store']);
        });

        Route::prefix('entries')->group(function () {
            Route::get('/', [EntryController::class, 'index']);
            Route::get('/stats/week', [EntryController::class, 'weeklyStats']);
            Route::get('/stats', [EntryController::class, 'stats']);
            Route::get('/{id}', [EntryController::class, 'show']);
            Route::post('/', [EntryController::class, 'store']);
            Route::patch('{id}', [EntryController::class, 'update']);
            Route::delete('{id}', [EntryController::class, 'destroy']);
            Route::post('{id}/share', [EntryShareController::class, 'store']);
        });

        Route::prefix('reports')->group(function () {
            Route::post('/', [ReportController::class, 'generate']);
            Route::post('/summary', [ReportController::class, 'summary']);
            Route::post('/multi-time-series', [ReportController::class, 'multiTimeSeries']);
            Route::post('/time-series', [ReportController::class, 'timeSeries']);
            Route::post('/grouped', [ReportController::class, 'grouped']);
            Route::post('/export', [ReportController::class, 'export']);
            Route::get('/weekly-summary', [ReportController::class, 'weeklySummary']);
            Route::get('/monthly-summary', [ReportController::class, 'monthlySummary']);
            Route::get('/predefined/budget', [ReportController::class, 'predefinedBudget']);
            Route::get('/predefined/medicine', [ReportController::class, 'predefinedMedicine']);
            Route::get('/predefined/weight', [ReportController::class, 'predefinedWeight']);
        });

        Route::prefix('dashboard')->group(function () {
            Route::get('/week', [DashboardController::class, 'weekSummary']);
            Route::get('/month', [DashboardController::class, 'monthSummary']);
            Route::get('/trends', [DashboardController::class, 'trends']);
        });

        Route::prefix('payments')->controller(PaymentController::class)->group(function () {
            Route::get('/overview', 'overview');
            Route::get('/categories', 'categories');
            Route::post('/categories', 'storeCategory');
            Route::patch('/categories/{id}', 'updateCategory');
            Route::delete('/categories/{id}', 'destroyCategory');
            Route::get('/plans', 'plans');
            Route::post('/plans', 'storePlan');
            Route::get('/plans/{id}', 'showPlan');
            Route::patch('/plans/{id}', 'updatePlan');
            Route::delete('/plans/{id}', 'destroyPlan');
            Route::post('/plans/{id}/close', 'closePlan');
            Route::get('/occurrences', 'occurrences');
            Route::post('/plans/{planId}/occurrences', 'storeOccurrence');
            Route::patch('/occurrences/{id}', 'updateOccurrence');
            Route::delete('/occurrences/{id}', 'destroyOccurrence');
            Route::post('/occurrences/{id}/pay', 'pay');
            Route::post('/occurrences/{id}/reopen', 'reopen');
        });
    });
});
