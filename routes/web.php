<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('home');
    });

    Route::get('/tr', function () {
        return view('tr.home');
        });

        Route::get('/about', function () {
            return view('about');
            });
            Route::get('/tr/about', function () {
                return view('tr.about');
                });

                Route::get('/blog', function () {
                    return view('blog');
                    });
                    Route::get('/blog/why-technique-alone-wont-save-you', function () {
                        return view('blog-post');
                        });

                        Route::get('/tr/blog', function () {
                            return view('tr.blog');
                            });
                            Route::get('/tr/blog/teknik-tek-basina-seni-kurtarmaz', function () {
                                return view('tr.blog-post');
                                });

                                Route::get('/system-cache-flush-7k2p9x', function () {
                                    Artisan::call('view:clear');
                                        Artisan::call('cache:clear');
                                            Artisan::call('config:clear');
                                                Artisan::call('route:clear');

                                                    return '<pre>Cache cleared successfully.</pre>';
                                                    });

                                                    Route::get('/system-create-admin-8kf3nx', function (\Illuminate\Http\Request $request) {
                                                        $email = $request->query('email');
                                                            $password = $request->query('password');
                                                                if (!$email || !$password) {
                                                                        return 'استفاده: به انتهای همین آدرس اضافه کن ?email=you@example.com&password=yourpassword';
                                                                            }
                                                                                $user = \App\Models\User::updateOrCreate(
                                                                                        ['email' => $email],
                                                                                                ['name' => 'Ehsan Dibazar', 'password' => bcrypt($password)]
                                                                                                    );
                                                                                                        return 'کاربر ادمین ساخته شد: ' . $user->email;
                                                                                                        });