<div class="min-h-screen bg-[var(--erp-bg-base)] text-[var(--erp-text-primary)] font-sans antialiased overflow-x-hidden">
    <!-- Navigation -->
    <nav class="sticky top-0 z-50 w-full border-b border-[var(--erp-border)] bg-[var(--erp-bg-base)]/80 backdrop-blur-md">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex shrink-0 items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] text-white">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                        </svg>
                    </div>
                    <span class="text-xl font-bold tracking-tight">Agentic BOS</span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('login') }}" class="text-sm font-semibold text-[var(--erp-text-secondary)] hover:text-[var(--erp-text-primary)] transition">Login</a>
                    @if ($salesWhatsApp)
                        <a href="https://wa.me/{{ $salesWhatsApp }}" target="_blank" rel="noopener noreferrer" class="inline-flex h-9 items-center justify-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[var(--erp-accent-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--erp-accent)] transition">
                            Konsultasi Gratis
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <main>
        <div class="relative pt-14 pb-20 sm:pt-20 lg:pt-32 lg:pb-28">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center">
                <div class="mx-auto max-w-3xl">
                    <div class="mb-8 flex justify-center">
                        <span class="inline-flex items-center gap-2 rounded-full border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-3 py-1 text-sm font-medium text-[var(--erp-accent)] shadow-sm">
                            <span class="flex h-2 w-2 rounded-full bg-[var(--erp-accent)] animate-pulse"></span>
                            All-in-One Business Assistant
                        </span>
                    </div>
                    <h1 class="text-4xl font-extrabold tracking-tight text-[var(--erp-text-primary)] sm:text-6xl">
                        Kendalikan Bisnis Anda dengan <span class="text-transparent bg-clip-text bg-gradient-to-r from-[var(--erp-accent)] to-purple-500">Business Intelligence</span>
                    </h1>
                    <p class="mt-6 text-lg leading-8 text-[var(--erp-text-secondary)]">
                        Bukan sekadar asisten. Agentic BOS memiliki keahlian Business Intelligence tingkat tinggi untuk merencanakan, mengawasi, dan mengoptimalkan seluruh aspek bisnis Anda secara real-time.
                    </p>
                    <div class="mt-10 flex items-center justify-center gap-x-6">
                        {{-- Tanpa nomor sales, ajakan tetap ada tapi mengarah ke
                             pendaftaran. Hero tanpa satu pun tombol lebih buruk
                             daripada tombol yang berbeda tujuan. --}}
                        <a href="{{ $salesWhatsApp ? 'https://wa.me/'.$salesWhatsApp : route('register') }}" @if ($salesWhatsApp) target="_blank" rel="noopener noreferrer" @endif class="inline-flex h-12 items-center justify-center gap-2 rounded-[var(--erp-radius-lg)] bg-[var(--erp-accent)] px-8 text-base font-semibold text-white shadow-sm hover:bg-[var(--erp-accent-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--erp-accent)] transition hover:-translate-y-0.5">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                            </svg>
                            {{ $salesWhatsApp ? 'Mulai Transformasi via WhatsApp' : 'Mulai Sekarang' }}
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Abstract background glow -->
            <div class="absolute inset-x-0 -top-40 -z-10 transform-gpu overflow-hidden blur-3xl sm:-top-80" aria-hidden="true">
                <div class="relative left-[calc(50%-11rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 rotate-[30deg] bg-gradient-to-tr from-[var(--erp-accent)] to-[#9089fc] opacity-20 sm:left-[calc(50%-30rem)] sm:w-[72.1875rem]" style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)"></div>
            </div>
        </div>

        <!-- AI Skills Matrix Section -->
        <div class="py-24 sm:py-32">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-base font-semibold leading-7 text-[var(--erp-accent)]">Kemampuan Inti</h2>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-[var(--erp-text-primary)] sm:text-4xl">Business Intelligence Skills</p>
                    <p class="mt-6 text-lg leading-8 text-[var(--erp-text-secondary)]">Agentic BOS diprogram dengan standar C-Level untuk menganalisis dan mengambil keputusan krusial di berbagai divisi.</p>
                </div>

                <div class="mx-auto mt-16 max-w-2xl sm:mt-20 lg:mt-24 lg:max-w-none">
                    <dl class="grid max-w-xl grid-cols-1 gap-x-8 gap-y-16 lg:max-w-none lg:grid-cols-3">
                        
                        <!-- Skill 1 -->
                        <div class="flex flex-col group rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-8 shadow-[var(--erp-card-shadow)] hover:border-[var(--erp-accent)] transition hover:-translate-y-1">
                            <dt class="flex items-center gap-x-3 text-base font-semibold leading-7 text-[var(--erp-text-primary)]">
                                <div class="flex h-10 w-10 items-center justify-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent-soft)] text-[var(--erp-accent)] group-hover:bg-[var(--erp-accent)] group-hover:text-white transition">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                                    </svg>
                                </div>
                                Financial Health (CFO-Level)
                            </dt>
                            <dd class="mt-4 flex flex-auto flex-col text-base leading-7 text-[var(--erp-text-secondary)]">
                                <p class="flex-auto">Pemantauan arus kas, analisis margin, pengawasan profitabilitas, dan diagnosa kesehatan keuangan secara otomatis.</p>
                            </dd>
                        </div>

                        <!-- Skill 2 -->
                        <div class="flex flex-col group rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-8 shadow-[var(--erp-card-shadow)] hover:border-[var(--erp-accent)] transition hover:-translate-y-1">
                            <dt class="flex items-center gap-x-3 text-base font-semibold leading-7 text-[var(--erp-text-primary)]">
                                <div class="flex h-10 w-10 items-center justify-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent-soft)] text-[var(--erp-accent)] group-hover:bg-[var(--erp-accent)] group-hover:text-white transition">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                        <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                        <line x1="12" y1="22.08" x2="12" y2="12" />
                                    </svg>
                                </div>
                                Operations & KPI
                            </dt>
                            <dd class="mt-4 flex flex-auto flex-col text-base leading-7 text-[var(--erp-text-secondary)]">
                                <p class="flex-auto">Memonitor alur kerja (workflow), mengevaluasi Key Performance Indicators secara berkala, dan merampingkan proses operasional.</p>
                            </dd>
                        </div>

                        <!-- Skill 3 -->
                        <div class="flex flex-col group rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-8 shadow-[var(--erp-card-shadow)] hover:border-[var(--erp-accent)] transition hover:-translate-y-1">
                            <dt class="flex items-center gap-x-3 text-base font-semibold leading-7 text-[var(--erp-text-primary)]">
                                <div class="flex h-10 w-10 items-center justify-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent-soft)] text-[var(--erp-accent)] group-hover:bg-[var(--erp-accent)] group-hover:text-white transition">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                                    </svg>
                                </div>
                                Business Projection & Plan
                            </dt>
                            <dd class="mt-4 flex flex-auto flex-col text-base leading-7 text-[var(--erp-text-secondary)]">
                                <p class="flex-auto">Peramalan pasar, proyeksi tren, dan perencanaan strategi bisnis dengan rekomendasi berbasis data riil.</p>
                            </dd>
                        </div>

                        <!-- Skill 4 -->
                        <div class="flex flex-col group rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-8 shadow-[var(--erp-card-shadow)] hover:border-[var(--erp-accent)] transition hover:-translate-y-1">
                            <dt class="flex items-center gap-x-3 text-base font-semibold leading-7 text-[var(--erp-text-primary)]">
                                <div class="flex h-10 w-10 items-center justify-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent-soft)] text-[var(--erp-accent)] group-hover:bg-[var(--erp-accent)] group-hover:text-white transition">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                        <circle cx="9" cy="7" r="4" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 3.13a4 4 0 0 1 0 7.75" />
                                    </svg>
                                </div>
                                HRD & Team Productivity
                            </dt>
                            <dd class="mt-4 flex flex-auto flex-col text-base leading-7 text-[var(--erp-text-secondary)]">
                                <p class="flex-auto">Mengukur indeks produktivitas tim, manajemen SDM, dan evaluasi beban kerja untuk hasil yang optimal.</p>
                            </dd>
                        </div>
                        
                        <!-- Core 5 -->
                        <div class="flex flex-col group rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-8 shadow-[var(--erp-card-shadow)] hover:border-[var(--erp-accent)] transition hover:-translate-y-1">
                            <dt class="flex items-center gap-x-3 text-base font-semibold leading-7 text-[var(--erp-text-primary)]">
                                <div class="flex h-10 w-10 items-center justify-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent-soft)] text-[var(--erp-accent)] group-hover:bg-[var(--erp-accent)] group-hover:text-white transition">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8h.01" />
                                    </svg>
                                </div>
                                Business Health Diagnosis
                            </dt>
                            <dd class="mt-4 flex flex-auto flex-col text-base leading-7 text-[var(--erp-text-secondary)]">
                                <p class="flex-auto">Pengecekan dan audit menyeluruh atas kesehatan bisnis dengan memberikan skor indeks yang dapat diukur.</p>
                            </dd>
                        </div>

                    </dl>
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="relative isolate overflow-hidden bg-[var(--erp-bg-base)]">
            <div class="px-6 py-24 sm:px-6 sm:py-32 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-[var(--erp-text-primary)] sm:text-4xl">Siap Meningkatkan Skala Bisnis Anda?</h2>
                    <p class="mx-auto mt-6 max-w-xl text-lg leading-8 text-[var(--erp-text-secondary)]">
                        Diskusikan kebutuhan bisnis Anda langsung dengan pakar kami, dan cari tahu bagaimana Agentic BOS dapat mempercepat pertumbuhan perusahaan Anda.
                    </p>
                    <div class="mt-10 flex items-center justify-center gap-x-6">
                        <a href="{{ $salesWhatsApp ? 'https://wa.me/'.$salesWhatsApp : route('register') }}" @if ($salesWhatsApp) target="_blank" rel="noopener noreferrer" @endif class="rounded-[var(--erp-radius-lg)] bg-[var(--erp-accent)] px-8 py-3.5 text-sm font-semibold text-white shadow-sm hover:bg-[var(--erp-accent-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--erp-accent)] transition">
                            {{ $salesWhatsApp ? 'Konsultasi via WhatsApp Sekarang' : 'Buat Akun Sekarang' }}
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="absolute inset-x-0 -bottom-40 -z-10 transform-gpu overflow-hidden blur-3xl sm:-bottom-80" aria-hidden="true">
                <div class="relative left-[calc(50%+11rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 rotate-[30deg] bg-gradient-to-tr from-[#9089fc] to-[var(--erp-accent)] opacity-20 sm:left-[calc(50%+30rem)] sm:w-[72.1875rem]" style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)"></div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-[var(--erp-border)] bg-[var(--erp-bg-base)]">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                <div class="flex items-center gap-2">
                    <div class="flex h-6 w-6 items-center justify-center rounded-[var(--erp-radius-sm)] bg-[var(--erp-accent)] text-white">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                        </svg>
                    </div>
                    <span class="font-bold text-[var(--erp-text-primary)]">Agentic BOS</span>
                </div>
                <p class="text-center text-xs leading-5 text-[var(--erp-text-muted)]">
                    &copy; {{ date('Y') }} Nalar Army. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
</div>
