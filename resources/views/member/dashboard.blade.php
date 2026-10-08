<x-layouts::app :title="__('Dashboard Member')">
    <div class="flex min-h-[60vh] w-full flex-1 flex-col items-center justify-center gap-4">
        <div id="qr-reader-wrap" class="hidden w-full max-w-sm flex-col items-center gap-2">
            <div
                id="qr-reader"
                class="w-full overflow-hidden rounded-xl border border-silver/60 bg-black dark:border-white/10"
                style="min-height: 300px;"
            ></div>

            <p id="scan-status" class="text-center text-sm text-ink-soft dark:text-silver">
                {{ __('Mencari kamera…') }}
            </p>

            <select
                id="camera-select"
                class="hidden w-full rounded-lg border border-silver/60 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-white/10 dark:bg-ash dark:text-zinc-100"
            ></select>
        </div>

        <p id="scan-message" class="hidden max-w-sm text-center text-sm text-brand dark:text-red-400"></p>

        <button
            type="button"
            id="btn-start-camera"
            class="h-40 w-40 rounded-lg border border-silver/60 bg-white text-sm font-medium text-zinc-900 dark:border-white/10 dark:bg-ash dark:text-zinc-100"
        >
            {{ __('Mulai Kamera') }}
        </button>

        <button
            type="button"
            id="btn-cancel-camera"
            class="hidden rounded-lg border border-silver/60 bg-white px-6 py-2 text-sm font-medium text-zinc-900 dark:border-white/10 dark:bg-ash dark:text-zinc-100"
        >
            {{ __('Batal') }}
        </button>
    </div>

    <style>
        #qr-reader video {
            width: 100% !important;
            height: auto !important;
            min-height: 280px;
            object-fit: cover;
            display: block !important;
            background: #000;
            border-radius: 12px;
        }

        #qr-reader canvas,
        #qr-reader img {
            max-width: 100%;
        }

        #qr-reader__scan_region {
            min-height: 280px;
            background: #000;
        }

        #qr-reader__dashboard_section {
            padding: 0 !important;
        }
    </style>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        (function () {
            // Guard agar init tidak dobel saat Livewire navigate / re-render.
            if (window.__gastonScannerInit) {
                return;
            }

            window.__gastonScannerInit = true;

            var startBtn = document.getElementById('btn-start-camera');
            var cancelBtn = document.getElementById('btn-cancel-camera');
            var wrapEl = document.getElementById('qr-reader-wrap');
            var readerEl = document.getElementById('qr-reader');
            var messageEl = document.getElementById('scan-message');
            var statusEl = document.getElementById('scan-status');
            var cameraSelect = document.getElementById('camera-select');
            var scanner = null;
            var busy = false;
            var bound = false;

            function showMessage(text) {
                messageEl.textContent = text;
                messageEl.classList.remove('hidden');
            }

            function hideMessage() {
                messageEl.textContent = '';
                messageEl.classList.add('hidden');
            }

            function showStatus(text) {
                statusEl.textContent = text;
            }

            function setScanningUI() {
                startBtn.classList.add('hidden');
                wrapEl.classList.remove('hidden');
                wrapEl.classList.add('flex');
                cancelBtn.classList.remove('hidden');
                readerEl.classList.add('scan-frame-active');
            }

            function setIdleUI() {
                startBtn.classList.remove('hidden');
                wrapEl.classList.add('hidden');
                wrapEl.classList.remove('flex');
                cancelBtn.classList.add('hidden');
                readerEl.classList.remove('scan-frame-active');
                showStatus('');
            }

            function friendlyError(err) {
                var name = err && err.name ? err.name : String(err);

                if (name === 'NotAllowedError' || name === 'SecurityError') {
                    return 'Izin kamera ditolak. Izinkan akses kamera di pengaturan browser lalu coba lagi.';
                }

                if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
                    return 'Tidak ada kamera yang ditemukan di perangkat ini.';
                }

                if (name === 'NotReadableError' || name === 'TrackStartError') {
                    return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi tersebut lalu coba lagi.';
                }

                return 'Kamera tidak dapat dibuka (' + name + '). Coba pilih kamera lain di bawah lalu tekan Batal dan mulai ulang.';
            }

            /**
             * Resolve hasil decode menjadi URL absen milik aplikasi ini.
             * Hanya URL dengan host yang sama dan path /absen/* yang
             * diizinkan untuk redirect otomatis (mencegah QR jahat).
             */
            function buildScanUrl(decodedText) {
                var text = (decodedText || '').trim();

                if (! text) {
                    return null;
                }

                try {
                    var url = new URL(text);

                    if (url.host !== window.location.host) {
                        return null;
                    }

                    if (url.pathname.indexOf('/absen/') !== 0) {
                        return null;
                    }

                    return url.toString();
                } catch (e) {
                    if (/^[A-Za-z0-9_-]{1,255}$/.test(text)) {
                        return window.location.origin + '/absen/' + text;
                    }

                    return null;
                }
            }

            function stopScanner(done) {
                if (! scanner) {
                    setIdleUI();
                    done();

                    return;
                }

                var current = scanner;
                scanner = null;

                current.stop().then(function () {
                    try {
                        current.clear();
                    } catch (e) {
                        // abaikan, elemen sudah dibersihkan
                    }

                    setIdleUI();
                    done();
                }).catch(function () {
                    setIdleUI();
                    done();
                });
            }

            function onScanSuccess(decodedText) {
                var target = buildScanUrl(decodedText);

                stopScanner(function () {
                    if (target) {
                        window.location.href = target;
                    } else {
                        showMessage('QR tersebut bukan QR absen Gaston.');
                    }
                });
            }

            function fillCameraSelect(cameras) {
                cameraSelect.innerHTML = '';

                if (! cameras || cameras.length === 0) {
                    cameraSelect.classList.add('hidden');

                    return;
                }

                if (cameras.length < 2) {
                    cameraSelect.classList.add('hidden');

                    return;
                }

                cameras.forEach(function (cam, idx) {
                    var opt = document.createElement('option');
                    opt.value = cam.id;
                    opt.textContent = cam.label || ('Kamera ' + (idx + 1));
                    cameraSelect.appendChild(opt);
                });

                // Prefer kamera belakang kalau labelnya ketahuan.
                var backIdx = -1;

                cameras.forEach(function (cam, idx) {
                    var label = (cam.label || '').toLowerCase();

                    if (backIdx === -1 && (label.indexOf('back') !== -1 || label.indexOf('rear') !== -1 || label.indexOf('belakang') !== -1 || label.indexOf('environment') !== -1)) {
                        backIdx = idx;
                    }
                });

                cameraSelect.selectedIndex = backIdx !== -1 ? backIdx : cameras.length - 1;
                cameraSelect.classList.remove('hidden');
            }

            function resolveCameraConfig(cameras) {
                // Kalau user sudah pilih manual, hormati pilihan itu.
                if (! cameraSelect.classList.contains('hidden') && cameraSelect.value) {
                    return { deviceId: { exact: cameraSelect.value } };
                }

                if (cameras && cameras.length > 0) {
                    // Label kosong sebelum izin diberikan, jadi pakai heuristik:
                    // kamera terakhir biasanya kamera belakang di HP.
                    var last = cameras[cameras.length - 1];

                    if (last && last.id) {
                        return { deviceId: { exact: last.id } };
                    }
                }

                return { facingMode: 'environment' };
            }

            function startWithCamera(cameraConfig, isRetry) {
                // Kosongkan dulu agar perhitungan ukuran video tidak 0
                // (penyebab umum view hitam/kosong).
                readerEl.innerHTML = '';
                scanner = new Html5Qrcode('qr-reader', { verbose: false });

                var config = {
                    fps: 10,
                    qrbox: function (viewfinderWidth, viewfinderHeight) {
                        var edge = Math.min(viewfinderWidth, viewfinderHeight);
                        edge = Math.max(200, Math.min(280, Math.floor(edge * 0.8)));

                        return { width: edge, height: edge };
                    },
                    aspectRatio: 1.0,
                };

                showStatus('Membuka kamera… arahkan ke QR absen.');

                scanner.start(
                    cameraConfig,
                    config,
                    onScanSuccess,
                    function () { /* abaikan frame tanpa QR */ }
                ).then(function () {
                    busy = false;
                    showStatus('Arahkan kamera ke QR absen.');
                }).catch(function (err) {
                    var name = err && err.name ? err.name : '';

                    // Kamera belakang tidak ada (mis. laptop): coba kamera apa pun.
                    if (! isRetry && (name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError')) {
                        startWithCamera(resolveCameraConfig([]), true);

                        return;
                    }

                    // deviceId exact gagal (mis. kamera dicabut): fallback facingMode.
                    if (! isRetry && cameraConfig && cameraConfig.deviceId) {
                        startWithCamera({ facingMode: 'environment' }, true);

                        return;
                    }

                    scanner = null;
                    busy = false;
                    setIdleUI();
                    showMessage(friendlyError(err));
                });
            }

            function startFlow() {
                if (busy) {
                    return;
                }

                hideMessage();

                if (typeof Html5Qrcode === 'undefined') {
                    showMessage('Library scanner gagal dimuat. Periksa koneksi internet lalu coba lagi.');

                    return;
                }

                if (! window.isSecureContext) {
                    showMessage('Kamera membutuhkan koneksi HTTPS. Buka halaman ini lewat URL https.');

                    return;
                }

                if (! (navigator.mediaDevices && navigator.mediaDevices.getUserMedia)) {
                    showMessage('Browser ini tidak mendukung akses kamera.');

                    return;
                }

                busy = true;
                // Tampilkan wadah dulu supaya <video> punya ukuran (tidak 0).
                setScanningUI();
                showStatus('Mencari kamera…');

                var getCameras = Html5Qrcode.getCameras
                    ? Html5Qrcode.getCameras()
                    : Promise.resolve([]);

                getCameras.then(function (cameras) {
                    fillCameraSelect(cameras || []);
                    startWithCamera(resolveCameraConfig(cameras || []), false);
                }).catch(function () {
                    startWithCamera({ facingMode: 'environment' }, false);
                });
            }

            function bind() {
                if (bound) {
                    return;
                }

                bound = true;
                startBtn.addEventListener('click', startFlow);

                cancelBtn.addEventListener('click', function () {
                    if (busy) {
                        return;
                    }

                    busy = true;
                    hideMessage();
                    showStatus('Menutup kamera…');
                    stopScanner(function () {
                        busy = false;
                    });
                });

                cameraSelect.addEventListener('change', function () {
                    if (! scanner || busy) {
                        return;
                    }

                    busy = true;
                    showStatus('Beralih kamera…');

                    stopScanner(function () {
                        setScanningUI();
                        startWithCamera({ deviceId: { exact: cameraSelect.value } }, true);
                    });
                });

                // Bersihkan stream saat pindah halaman (Livewire navigate / tutup tab).
                window.addEventListener('pagehide', function () {
                    if (scanner) {
                        try {
                            scanner.stop().catch(function () {});
                        } catch (e) {
                            // abaikan
                        }
                    }
                });

                document.addEventListener('livewire:navigate', function () {
                    if (scanner) {
                        try {
                            scanner.stop().catch(function () {});
                        } catch (e) {
                            // abaikan
                        }

                        scanner = null;
                    }
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bind);
            } else {
                bind();
            }

            document.addEventListener('livewire:navigated', bind);
        })();
    </script>
</x-layouts::app>
