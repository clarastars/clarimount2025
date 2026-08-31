@extends('landing.layout')

@section('content')
    @include('landing.partials.hero')

    @include('landing.partials.features')

    @include('landing.partials.pricing')

    @include('landing.partials.cta')

    <section id="about" class="bg-white py-20 lg:py-24">
        <div class="container mx-auto px-4 sm:px-6">
            <div class="mx-auto max-w-3xl text-center landing-fade-in">
                <div class="landing-section-badge mb-4">من نحن</div>
                <h2 class="mb-5 text-3xl font-bold text-slate-900 sm:text-4xl">
                    شريكك في <span class="landing-gradient-text">إدارة الموارد البشرية</span>
                </h2>
                <p class="text-lg leading-relaxed text-slate-600">
                    نحن فريق متخصص في تطوير حلول إدارة الموارد البشرية التي تساعد الشركات والمؤسسات على إدارة موظفيها بكفاءة، وفق أعلى معايير الأمان والامتثال.
                </p>
            </div>
        </div>
    </section>

    <section id="contact" class="bg-slate-50 py-20 lg:py-24">
        <div class="container mx-auto px-4 sm:px-6">
            <div class="mb-12 text-center landing-fade-in">
                <div class="landing-section-badge mb-4">اتصل بنا</div>
                <h2 class="text-3xl font-bold text-slate-900 sm:text-4xl">نحن هنا للمساعدة</h2>
            </div>
            <div class="mx-auto grid max-w-2xl gap-4 sm:grid-cols-2 landing-fade-in">
                <div class="rounded-xl border border-slate-200 bg-white p-6 text-center shadow-sm">
                    <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="mb-1 font-bold text-slate-900">البريد الإلكتروني</h3>
                    <a href="mailto:support@example.com" class="text-sm text-blue-600 hover:text-blue-700">support@example.com</a>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-6 text-center shadow-sm">
                    <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-xl bg-green-50 text-green-600">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="mb-1 font-bold text-slate-900">ساعات العمل</h3>
                    <p class="text-sm text-slate-500">الأحد — الخميس<br>9:00 ص — 6:00 م</p>
                </div>
            </div>
        </div>
    </section>
@endsection
