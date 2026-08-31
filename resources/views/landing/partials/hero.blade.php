<section class="landing-hero-bg relative overflow-hidden pt-28 pb-16 lg:pt-36 lg:pb-24">
    <div class="container mx-auto px-4 sm:px-6">
        <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
            {{-- Content --}}
            <div class="landing-fade-in text-center lg:text-right">
                <div class="landing-section-badge mb-5">
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    منصة موثوقة للمؤسسات
                </div>

                <h1 class="mb-5 text-4xl font-bold leading-tight text-slate-900 sm:text-5xl lg:text-[3.25rem]">
                    إدارة موارد بشرية
                    <span class="landing-gradient-text block mt-1">باحترافية المؤسسات الكبرى</span>
                </h1>

                <p class="mx-auto mb-8 max-w-xl text-lg leading-relaxed text-slate-600 lg:mx-0">
                    منصة متكاملة للحضور، الرواتب، الإجازات، والتقارير — مصممة لتلبية احتياجات الشركات والمؤسسات.
                </p>

                <div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start">
                    <a href="{{ route('login') }}" class="landing-btn-primary landing-btn-primary-lg w-full sm:w-auto">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                        تسجيل الدخول إلى البوابة
                    </a>
                    <a href="#features" class="landing-btn-outline w-full sm:w-auto">
                        استكشف المميزات
                    </a>
                </div>

                <div class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:gap-4">
                    @php
                    $stats = [
                        ['value' => '+5000', 'label' => 'شركة'],
                        ['value' => '+50K', 'label' => 'موظف'],
                        ['value' => '98%', 'label' => 'رضا العملاء'],
                        ['value' => '24/7', 'label' => 'دعم فني'],
                    ];
                    @endphp
                    @foreach($stats as $stat)
                        <div class="landing-stat-card">
                            <div class="text-xl font-bold text-blue-600 lg:text-2xl">{{ $stat['value'] }}</div>
                            <div class="mt-0.5 text-xs text-slate-500">{{ $stat['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Login portal card --}}
            <div class="landing-fade-in mx-auto w-full max-w-md lg:mx-0 lg:ms-auto" style="transition-delay: 0.12s">
                <div class="landing-login-card overflow-hidden">
                    <div class="landing-login-card-header">
                        <div class="flex items-center gap-3">
                            <div class="flex size-11 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20">
                                <svg class="size-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-white">بوابة الدخول</h2>
                                <p class="text-sm text-slate-300">للموظفين ومديري الموارد البشرية</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5 p-6 sm:p-8">
                        <p class="text-center text-sm leading-relaxed text-slate-600">
                            سجّل دخولك باستخدام <strong class="font-semibold text-slate-800">إيميل العمل</strong> للوصول إلى لوحة التحكم، طلبات الإجازة، كشوف الرواتب، والمزيد.
                        </p>

                        <a href="{{ route('login') }}" class="landing-btn-primary landing-btn-primary-lg w-full">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                            دخول إلى حسابي
                        </a>

                        <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                            <ul class="space-y-2 text-sm text-slate-600">
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    دخول آمن برمز تحقق أو كلمة مرور
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    متاح للموظفين والإدارة
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="size-4 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    دعم فني على مدار الساعة
                                </li>
                            </ul>
                        </div>

                        <p class="text-center text-xs text-slate-400">
                            ليس لديك حساب؟
                            <a href="{{ route('register') }}" class="font-medium text-blue-600 hover:text-blue-700">تواصل مع إدارة شركتك</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
