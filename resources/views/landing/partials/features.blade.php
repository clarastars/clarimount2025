@php
$features = [
    [
        'icon' => 'users',
        'title' => 'إدارة الموظفين',
        'description' => 'احتفظ بسجلات كاملة للموظفين مع إمكانية الوصول السريع لجميع البيانات'
    ],
    [
        'icon' => 'calendar',
        'title' => 'تتبع الحضور والغياب',
        'description' => 'نظام ذكي لمراقبة الحضور والانصراف مع تقارير تفصيلية'
    ],
    [
        'icon' => 'dollar-sign',
        'title' => 'إدارة الرواتب',
        'description' => 'احسب الرواتب والمكافآت تلقائياً مع خيارات متقدمة للخصومات والإضافات'
    ],
    [
        'icon' => 'trending-up',
        'title' => 'تقييم الأداء',
        'description' => 'نظام متطور لتقييم أداء الموظفين وتحديد أهداف واضحة'
    ],
    [
        'icon' => 'file-text',
        'title' => 'التقارير والتحليلات',
        'description' => 'تقارير شاملة ورسوم بيانية تفاعلية لاتخاذ قرارات مبنية على البيانات'
    ],
    [
        'icon' => 'shield',
        'title' => 'الأمان والخصوصية',
        'description' => 'حماية عالية المستوى لبيانات شركتك مع نسخ احتياطي يومي'
    ]
];

$icons = [
    'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>',
    'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>',
    'dollar-sign' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
    'trending-up' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>',
    'file-text' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>',
    'shield' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>'
];
@endphp

<section id="features" class="bg-white py-20 lg:py-24">
    <div class="container mx-auto px-4 sm:px-6">
        <div class="mb-14 text-center landing-fade-in">
            <div class="landing-section-badge mb-4">المميزات</div>
            <h2 class="mb-4 text-3xl font-bold text-slate-900 sm:text-4xl">
                حلول متكاملة <span class="landing-gradient-text">للمؤسسات</span>
            </h2>
            <p class="mx-auto max-w-2xl text-lg text-slate-600">
                كل ما تحتاجه لإدارة مواردك البشرية بكفاءة واحترافية
            </p>
        </div>

        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach($features as $index => $feature)
                <div class="landing-feature-card landing-fade-in" style="transition-delay: {{ ($index % 3) * 0.08 }}s">
                    <div class="mb-5 flex size-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            {!! $icons[$feature['icon']] !!}
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-slate-900">{{ $feature['title'] }}</h3>
                    <p class="text-sm leading-relaxed text-slate-600">{{ $feature['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

