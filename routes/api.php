<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\EnrollmentController;

Route::prefix('v1')->group(function () {
    
    // Authentication routes
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    
    // Public course routes
    Route::get('courses', [CourseController::class, 'index']);
    Route::get('courses/{id}', [CourseController::class, 'show']);
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        
        // Auth user routes
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        
        // Course management (teachers/admins)
        Route::post('courses', [CourseController::class, 'store']);
        Route::put('courses/{id}', [CourseController::class, 'update']);
        Route::delete('courses/{id}', [CourseController::class, 'destroy']);
        
        // Enrollment routes
        Route::post('courses/{courseId}/enroll', [EnrollmentController::class, 'enroll']);
        Route::delete('courses/{courseId}/enroll', [EnrollmentController::class, 'unenroll']);
        Route::get('my-enrollments', [EnrollmentController::class, 'myEnrollments']);
        
        // User profile routes
        Route::get('profile', function (Request $request) {
            return response()->json([
                'success' => true,
                'user' => $request->user()
            ]);
        });
        
        Route::put('profile', function (Request $request) {
            // Profile update logic would go here
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully'
            ]);
        });
        
        // Dashboard routes
        Route::get('dashboard', function (Request $request) {
            $user = $request->user();
            return response()->json([
                'success' => true,
                'dashboard' => [
                    'user' => [
                        'id' => $user->getId(),
                        'fullName' => $user->getFullName(),
                        'email' => $user->getEmail()
                    ],
                    'stats' => [
                        'enrolledCourses' => $user->getEnrollments()->count(),
                        'completedCourses' => $user->getEnrollments()->filter(function($e) {
                            return $e->isCompleted();
                        })->count(),
                        'teachingCourses' => $user->getTeachingCourses()->count()
                    ]
                ]
            ]);
        });
    });
});

// Health check route
Route::get('health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toISOString(),
        'environment' => app()->environment()
    ]);
});