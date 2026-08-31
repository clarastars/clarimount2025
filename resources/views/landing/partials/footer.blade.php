<footer class="bg-slate-950 pt-14 pb-8 text-slate-400">
    <div class="container mx-auto px-4 sm:px-6">
        <div class="mb-10 grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <div class="mb-4 flex items-center gap-2.5">
                    <img src="{{ asset('ITMAAL.png') }}" alt="إعتمال" class="h-9 w-auto">
                    <span class="text-lg font-bold text-white">إعتمال</span>
                </div>
                <p class="mb-5 text-sm leading-relaxed text-slate-500">
                    منصة موارد بشرية متكاملة للشركات والمؤسسات.
                </p>
                <a href="{{ route('login') }}" class="landing-btn-primary text-sm">
                    تسجيل الدخول
                </a>
            </div>

            <div>
                <h3 class="text-white text-lg mb-6">المنتج</h3>
                <ul class="space-y-3">
                    <li><a href="#features" class="hover:text-blue-400 transition-colors">المميزات</a></li>
                    <li><a href="#pricing" class="hover:text-blue-400 transition-colors">الأسعار</a></li>
                    <li><a href="#" class="hover:text-blue-400 transition-colors">التحديثات</a></li>
                    <li><a href="#" class="hover:text-blue-400 transition-colors">الأمان</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-white text-lg mb-6">الشركة</h3>
                <ul class="space-y-3">
                    <li><a href="#about" class="hover:text-blue-400 transition-colors">من نحن</a></li>
                    <li><a href="#" class="hover:text-blue-400 transition-colors">المدونة</a></li>
                    <li><a href="#" class="hover:text-blue-400 transition-colors">الوظائف</a></li>
                    <li><a href="#contact" class="hover:text-blue-400 transition-colors">اتصل بنا</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-white text-lg mb-6">الدعم</h3>
                <ul class="space-y-3">
                    <li><a href="#" class="hover:text-blue-400 transition-colors">مركز المساعدة</a></li>
                    <li><a href="#" class="hover:text-blue-400 transition-colors">الشروط والأحكام</a></li>
                    <li><a href="#" class="hover:text-blue-400 transition-colors">سياسة الخصوصية</a></li>
                    <li><a href="#" class="hover:text-blue-400 transition-colors">الأسئلة الشائعة</a></li>
                </ul>
            </div>
        </div>

        <div class="border-t border-slate-800 pt-6 text-center text-sm text-slate-500">
            <p>© {{ date('Y') }} إعتمال. جميع الحقوق محفوظة.</p>
        </div>
    </div>
</footer>

