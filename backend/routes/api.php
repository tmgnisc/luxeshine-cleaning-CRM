<?php

use App\Http\Controllers\Api\V1\Admin\ActivityLogController;
use App\Http\Controllers\Api\V1\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\V1\Admin\CalendarFeedController;
use App\Http\Controllers\Api\V1\Admin\CleanerController;
use App\Http\Controllers\Api\V1\Admin\CustomerAddressController;
use App\Http\Controllers\Api\V1\Admin\CustomerController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\ScheduleController;
use App\Http\Controllers\Api\V1\Admin\SearchController;
use App\Http\Controllers\Api\V1\Admin\ServiceCategoryController;
use App\Http\Controllers\Api\V1\Admin\ServiceController;
use App\Http\Controllers\Api\V1\Admin\SettingController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Cleaner\AvailabilityController;
use App\Http\Controllers\Api\V1\Cleaner\DashboardController;
use App\Http\Controllers\Api\V1\Cleaner\JobController;
use App\Http\Controllers\Api\V1\Cleaner\ReviewController as CleanerReviewController;
use App\Http\Controllers\Api\V1\Cleaner\TimeOffController;
use App\Http\Controllers\Api\V1\Cleaner\WorkingHourController;
use App\Http\Controllers\Api\V1\Customer\AddressController;
use App\Http\Controllers\Api\V1\Customer\BookingController as CustomerBookingController;
use App\Http\Controllers\Api\V1\Customer\ReviewController as CustomerReviewController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\Public\PublicController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
        Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:auth');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:auth');

        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware('signed')
            ->name('verification.verify');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::put('me', [AuthController::class, 'updateProfile']);
            Route::put('me/password', [AuthController::class, 'updatePassword']);
            Route::post('email/verify/send', [EmailVerificationController::class, 'resend'])->middleware('throttle:auth');
            Route::get('email/verify/status', [EmailVerificationController::class, 'status']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Public (no authentication)
    |--------------------------------------------------------------------------
    */
    Route::prefix('public')->group(function () {
        Route::get('settings', [PublicController::class, 'settings']);
        Route::get('service-categories', [PublicController::class, 'serviceCategories']);
        Route::get('services', [PublicController::class, 'services']);
        Route::get('services/{slug}', [PublicController::class, 'service']);
        Route::get('reviews', [PublicController::class, 'reviews']);
        Route::get('availability', [PublicController::class, 'availability']);
        Route::post('contact', [PublicController::class, 'contact'])->middleware('throttle:auth');
    });

    /*
    |--------------------------------------------------------------------------
    | Customer portal (role:customer)
    |--------------------------------------------------------------------------
    */
    Route::prefix('customer')->middleware(['auth:sanctum', 'role:customer'])->group(function () {
        Route::get('addresses', [AddressController::class, 'index']);
        Route::post('addresses', [AddressController::class, 'store']);
        Route::match(['put', 'patch'], 'addresses/{address}', [AddressController::class, 'update']);
        Route::delete('addresses/{address}', [AddressController::class, 'destroy']);
        Route::post('addresses/{address}/default', [AddressController::class, 'setDefault']);

        // Bookings
        Route::get('bookings', [CustomerBookingController::class, 'index']);
        Route::post('bookings', [CustomerBookingController::class, 'store'])->middleware('throttle:bookings');
        Route::get('bookings/{booking}', [CustomerBookingController::class, 'show']);
        Route::put('bookings/{booking}/reschedule', [CustomerBookingController::class, 'reschedule']);
        Route::post('bookings/{booking}/cancel', [CustomerBookingController::class, 'cancel']);
        Route::post('bookings/{booking}/review', [CustomerReviewController::class, 'store']);
    });

    /*
    |--------------------------------------------------------------------------
    | Cleaner portal (role:cleaner)
    |--------------------------------------------------------------------------
    */
    Route::prefix('cleaner')->middleware(['auth:sanctum', 'role:cleaner'])->group(function () {
        Route::put('availability', [AvailabilityController::class, 'update']);
        Route::get('working-hours', [WorkingHourController::class, 'index']);
        Route::put('working-hours', [WorkingHourController::class, 'update']);
        Route::get('time-off', [TimeOffController::class, 'index']);
        Route::post('time-off', [TimeOffController::class, 'store']);
        Route::delete('time-off/{timeOff}', [TimeOffController::class, 'destroy']);

        // Dashboard & jobs
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('jobs', [JobController::class, 'index']);
        Route::get('jobs/{booking}', [JobController::class, 'show']);
        Route::post('jobs/{booking}/start', [JobController::class, 'start']);
        Route::post('jobs/{booking}/complete', [JobController::class, 'complete']);

        // Reviews
        Route::get('reviews', [CleanerReviewController::class, 'index']);
    });

    /*
    |--------------------------------------------------------------------------
    | Notifications (any authenticated user)
    |--------------------------------------------------------------------------
    */
    Route::prefix('notifications')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('{id}/read', [NotificationController::class, 'markRead']);
        Route::post('read-all', [NotificationController::class, 'markAllRead']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin (admin + super_admin)
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function () {
        // Dashboard
        Route::get('dashboard/stats', [AdminDashboardController::class, 'stats']);
        Route::get('dashboard/revenue-chart', [AdminDashboardController::class, 'revenueChart']);
        Route::get('dashboard/bookings-by-service', [AdminDashboardController::class, 'bookingsByService']);
        Route::get('dashboard/today-jobs', [AdminDashboardController::class, 'todayJobs']);
        Route::get('dashboard/top-cleaners', [AdminDashboardController::class, 'topCleaners']);

        // Global search & activity log
        Route::get('search', [SearchController::class, 'index']);
        Route::get('activity-logs', [ActivityLogController::class, 'index']);

        // Reviews
        Route::get('reviews', [AdminReviewController::class, 'index']);
        Route::post('reviews/bulk-delete', [AdminReviewController::class, 'bulkDestroy']);
        Route::get('reviews/stats', [AdminReviewController::class, 'stats']);
        Route::patch('reviews/{review}/publish', [AdminReviewController::class, 'publish']);
        Route::put('reviews/{review}/reply', [AdminReviewController::class, 'reply']);
        Route::delete('reviews/{review}', [AdminReviewController::class, 'destroy']);

        // Services
        Route::get('services', [ServiceController::class, 'index']);
        Route::post('services', [ServiceController::class, 'store']);
        Route::post('services/bulk-delete', [ServiceController::class, 'bulkDestroy']);
        Route::post('services/reorder', [ServiceController::class, 'reorder']);
        Route::get('services/{service}', [ServiceController::class, 'show']);
        Route::match(['put', 'patch'], 'services/{service}', [ServiceController::class, 'update']);
        Route::patch('services/{service}/live', [ServiceController::class, 'toggleLive']);
        Route::delete('services/{service}', [ServiceController::class, 'destroy']);

        // Service categories
        Route::get('service-categories', [ServiceCategoryController::class, 'index']);
        Route::post('service-categories', [ServiceCategoryController::class, 'store']);
        Route::get('service-categories/{category}', [ServiceCategoryController::class, 'show']);
        Route::match(['put', 'patch'], 'service-categories/{category}', [ServiceCategoryController::class, 'update']);
        Route::delete('service-categories/{category}', [ServiceCategoryController::class, 'destroy']);

        // Bookings
        Route::get('bookings', [AdminBookingController::class, 'index']);
        Route::post('bookings', [AdminBookingController::class, 'store']);
        Route::post('bookings/bulk-delete', [AdminBookingController::class, 'bulkDestroy']);
        Route::get('bookings/status-counts', [AdminBookingController::class, 'statusCounts']);
        Route::get('bookings/export', [AdminBookingController::class, 'export']);
        Route::get('bookings/{booking}', [AdminBookingController::class, 'show']);
        Route::match(['put', 'patch'], 'bookings/{booking}', [AdminBookingController::class, 'update']);
        Route::patch('bookings/{booking}/status', [AdminBookingController::class, 'changeStatus']);
        Route::patch('bookings/{booking}/assign', [AdminBookingController::class, 'assign']);
        Route::patch('bookings/{booking}/paid', [AdminBookingController::class, 'paid']);
        Route::delete('bookings/{booking}', [AdminBookingController::class, 'destroy']);
        Route::get('available-cleaners', [AdminBookingController::class, 'availableCleaners']);

        // Schedule
        Route::get('schedule', [ScheduleController::class, 'index']);

        // Calendar feeds (Airbnb / third-party iCal integrations)
        Route::get('calendar-feeds', [CalendarFeedController::class, 'index']);
        Route::post('calendar-feeds', [CalendarFeedController::class, 'store']);
        Route::post('calendar-feeds/sync-all', [CalendarFeedController::class, 'syncAll']);
        Route::post('calendar-feeds/{calendarFeed}/sync', [CalendarFeedController::class, 'sync']);
        Route::match(['put', 'patch'], 'calendar-feeds/{calendarFeed}', [CalendarFeedController::class, 'update']);
        Route::delete('calendar-feeds/{calendarFeed}', [CalendarFeedController::class, 'destroy']);

        // Cleaners (staff)
        Route::get('cleaners', [CleanerController::class, 'index']);
        Route::post('cleaners', [CleanerController::class, 'store']);
        Route::post('cleaners/bulk-delete', [CleanerController::class, 'bulkDestroy']);
        Route::get('cleaners/verification-counts', [CleanerController::class, 'verificationCounts']);
        Route::get('cleaners/{cleaner}', [CleanerController::class, 'show']);
        Route::match(['put', 'patch'], 'cleaners/{cleaner}', [CleanerController::class, 'update']);
        Route::patch('cleaners/{cleaner}/availability', [CleanerController::class, 'availability']);
        Route::patch('cleaners/{cleaner}/verification', [CleanerController::class, 'verification']);
        Route::delete('cleaners/{cleaner}', [CleanerController::class, 'destroy']);

        // Customers
        Route::get('customers', [CustomerController::class, 'index']);
        Route::post('customers', [CustomerController::class, 'store']);
        Route::post('customers/bulk-delete', [CustomerController::class, 'bulkDestroy']);
        Route::get('customers/{customer}', [CustomerController::class, 'show']);
        Route::match(['put', 'patch'], 'customers/{customer}', [CustomerController::class, 'update']);
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);
        Route::get('customers/{customer}/bookings', [CustomerController::class, 'bookings']);
        Route::get('customers/{customer}/addresses', [CustomerAddressController::class, 'index']);
        Route::post('customers/{customer}/addresses', [CustomerAddressController::class, 'store']);
        Route::match(['put', 'patch'], 'customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'update']);
        Route::delete('customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'destroy']);

        // Settings
        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin users management (super_admin only)
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin/users')->middleware(['auth:sanctum', 'role:super_admin'])->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('{user}', [UserController::class, 'show']);
        Route::match(['put', 'patch'], '{user}', [UserController::class, 'update']);
        Route::delete('{user}', [UserController::class, 'destroy']);
    });
});
