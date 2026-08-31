<header id="landing-header" class="landing-nav fixed top-0 z-50 w-full">
    <div class="container mx-auto px-4 sm:px-6">
        <div class="landing-nav-shell">
            <div class="landing-nav-inner">
                <a href="/" class="landing-nav-brand group">
                    <div class="landing-nav-logo">
                        <img src="{{ asset('ITMAAL.png') }}" alt="إعتمال" class="size-7 object-contain">
                    </div>
                    <div class="hidden sm:block">
                        <span class="block text-[15px] font-bold text-slate-900 lg:text-base">إعتمال</span>
                        <span class="block text-[11px] font-medium text-slate-500">بوابة الموارد البشرية</span>
                    </div>
                </a>

                <nav class="landing-nav-pills hidden lg:flex">
                    <a href="#features" class="landing-nav-link" data-section="features">المميزات</a>
                    <a href="#pricing" class="landing-nav-link" data-section="pricing">الأسعار</a>
                    <a href="#about" class="landing-nav-link" data-section="about">من نحن</a>
                    <a href="#contact" class="landing-nav-link" data-section="contact">اتصل بنا</a>
                </nav>

                <div class="landing-nav-actions hidden lg:flex">
                    <a href="#contact" class="landing-nav-btn-ghost hidden xl:inline-flex">
                        تواصل معنا
                    </a>
                    <a href="{{ route('login') }}" class="landing-nav-login">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                        تسجيل الدخول
                    </a>
                </div>

                <button
                    id="mobile-menu-button"
                    type="button"
                    class="landing-nav-mobile-toggle lg:hidden"
                    onclick="toggleMobileMenu()"
                    aria-label="فتح القائمة"
                    aria-expanded="false"
                >
                    <svg id="menu-icon-open" class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                    <svg id="menu-icon-close" class="hidden size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div id="mobile-menu" class="landing-nav-mobile-menu hidden lg:hidden">
                <nav class="flex flex-col gap-1">
                    <a href="#features" class="mobile-menu-link landing-nav-link">المميزات</a>
                    <a href="#pricing" class="mobile-menu-link landing-nav-link">الأسعار</a>
                    <a href="#about" class="mobile-menu-link landing-nav-link">من نحن</a>
                    <a href="#contact" class="mobile-menu-link landing-nav-link">اتصل بنا</a>
                    <div class="mt-3 flex flex-col gap-2 border-t border-slate-200/80 pt-3">
                        <a href="{{ route('login') }}" class="mobile-menu-link landing-nav-login w-full justify-center">
                            تسجيل الدخول
                        </a>
                        <a href="#contact" class="mobile-menu-link landing-nav-btn-ghost w-full justify-center">
                            تواصل معنا
                        </a>
                    </div>
                </nav>
            </div>
        </div>
    </div>
</header>

@push('scripts')
<script>
    function toggleMobileMenu() {
        const menu = document.getElementById('mobile-menu');
        const button = document.getElementById('mobile-menu-button');
        const openIcon = document.getElementById('menu-icon-open');
        const closeIcon = document.getElementById('menu-icon-close');
        const isHidden = menu.classList.toggle('hidden');
        openIcon.classList.toggle('hidden', !isHidden);
        closeIcon.classList.toggle('hidden', isHidden);
        button.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
    }

    function closeMobileMenu() {
        const menu = document.getElementById('mobile-menu');
        const button = document.getElementById('mobile-menu-button');
        menu.classList.add('hidden');
        document.getElementById('menu-icon-open').classList.remove('hidden');
        document.getElementById('menu-icon-close').classList.add('hidden');
        button.setAttribute('aria-expanded', 'false');
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.mobile-menu-link').forEach(function(link) {
            link.addEventListener('click', closeMobileMenu);
        });

        const header = document.getElementById('landing-header');
        window.addEventListener('scroll', function() {
            header.classList.toggle('scrolled', window.scrollY > 24);
        }, { passive: true });

        const navLinks = document.querySelectorAll('.landing-nav-link[data-section]');
        const sections = Array.from(navLinks).map(function(link) {
            return document.getElementById(link.dataset.section);
        }).filter(Boolean);

        if (sections.length > 0) {
            const sectionObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        navLinks.forEach(function(link) {
                            link.classList.toggle('active', link.dataset.section === entry.target.id);
                        });
                    }
                });
            }, { rootMargin: '-40% 0px -50% 0px', threshold: 0 });

            sections.forEach(function(section) {
                sectionObserver.observe(section);
            });
        }

        const fadeObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });

        document.querySelectorAll('.landing-fade-in').forEach(function(el) {
            fadeObserver.observe(el);
        });
    });
</script>
@endpush
