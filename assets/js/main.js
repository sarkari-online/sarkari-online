/**
 * EduPulse - Master Client Logic (Vanilla JS, Zero Dependencies)
 * Handles mobile drawer, search modal, FAQ accordions, share dialogs & sticky header shadow.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Smart Sticky Header (Hide on Scroll Down, Instant Reveal on Scroll Up)
    const siteHeader = document.querySelector('.site-header');
    if (siteHeader) {
        let lastScrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        let isTicking = false;
        const scrollDeltaThreshold = 4; // Responsive delta threshold for direction changes
        const topClearance = 50; // Top boundary

        const onScroll = () => {
            const currentScrollY = Math.max(0, window.pageYOffset || document.documentElement.scrollTop || 0);
            
            // At the top of page
            if (currentScrollY <= topClearance) {
                siteHeader.classList.remove('header-hidden');
                siteHeader.classList.remove('header-pinned');
            } 
            // Scrolling DOWN -> Hide Header
            else if (currentScrollY > lastScrollY && (currentScrollY - lastScrollY) > scrollDeltaThreshold) {
                const langContainer = document.getElementById('langDropdownContainer');
                const isLangOpen = langContainer && langContainer.classList.contains('open');
                if (!isLangOpen) {
                    siteHeader.classList.add('header-hidden');
                    siteHeader.classList.remove('header-pinned');
                }
            } 
            // Scrolling UP -> Reveal Sticky Header immediately
            else if (currentScrollY < lastScrollY && (lastScrollY - currentScrollY) > scrollDeltaThreshold) {
                siteHeader.classList.remove('header-hidden');
                siteHeader.classList.add('header-pinned');
            }

            lastScrollY = currentScrollY;
            isTicking = false;
        };

        window.addEventListener('scroll', () => {
            if (!isTicking) {
                window.requestAnimationFrame(onScroll);
                isTicking = true;
            }
        }, { passive: true });
    }

    // 2. Mobile Drawer Navigation
    const mobileToggleBtn = document.querySelector('.mobile-menu-toggle');
    const mobileDrawer = document.querySelector('.mobile-nav-drawer');
    const mobileCloseBtn = document.querySelector('.mobile-nav-close');

    function openMobileMenu() {
        if (mobileDrawer) {
            mobileDrawer.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeMobileMenu() {
        if (mobileDrawer) {
            mobileDrawer.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    if (mobileToggleBtn) {
        mobileToggleBtn.addEventListener('click', openMobileMenu);
    }
    if (mobileCloseBtn) {
        mobileCloseBtn.addEventListener('click', closeMobileMenu);
    }
    if (mobileDrawer) {
        mobileDrawer.addEventListener('click', (e) => {
            if (e.target === mobileDrawer) {
                closeMobileMenu();
            }
        });
    }

    // 3. Search Modal Trigger & Keyboard Shortcut (Cmd+K / Ctrl+K / '/' key)
    const searchModal = document.querySelector('.search-modal');
    const searchTriggers = document.querySelectorAll('.trigger-search-modal');
    const searchCloseBtn = document.querySelector('.search-modal-close');
    const searchInput = document.querySelector('.search-modal-input');

    function openSearchModal() {
        if (searchModal) {
            searchModal.classList.add('active');
            document.body.style.overflow = 'hidden';
            if (searchInput) {
                setTimeout(() => searchInput.focus(), 100);
            }
        }
    }

    function closeSearchModal() {
        if (searchModal) {
            searchModal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    searchTriggers.forEach(btn => btn.addEventListener('click', (e) => {
        e.preventDefault();
        openSearchModal();
    }));

    if (searchCloseBtn) {
        searchCloseBtn.addEventListener('click', closeSearchModal);
    }

    if (searchModal) {
        searchModal.addEventListener('click', (e) => {
            if (e.target === searchModal) {
                closeSearchModal();
            }
        });
    }

    // Global Escape Key Listener
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeMobileMenu();
            closeSearchModal();
        }
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            openSearchModal();
        }
    });

    // 4. FAQ Accordion Interaction
    const faqButtons = document.querySelectorAll('.faq-question-btn');
    faqButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const item = btn.closest('.faq-accordion-item');
            if (item) {
                const isOpen = item.classList.contains('open');
                // Optional: close other open items in the same container
                const parent = item.parentElement;
                if (parent) {
                    parent.querySelectorAll('.faq-accordion-item').forEach(el => el.classList.remove('open'));
                }
                if (!isOpen) {
                    item.classList.add('open');
                }
            }
        });
    });

    // 5. Share Button / Web Share API / Copy Link
    const shareBtns = document.querySelectorAll('.js-share-btn');
    shareBtns.forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const title = btn.dataset.title || document.title;
            const url = btn.dataset.url || window.location.href;

            if (navigator.share) {
                try {
                    await navigator.share({ title, url });
                } catch (err) {
                    // Fallback to clipboard
                    copyToClipboard(url, btn);
                }
            } else {
                copyToClipboard(url, btn);
            }
        });
    });

    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Copied Link!';
            setTimeout(() => {
                btn.innerHTML = originalHtml;
            }, 2000);
        }).catch(() => {
            prompt('Copy article link:', text);
        });
    }

    // 7. Auto-wrap Article Tables in Responsive Container
    document.querySelectorAll('.article-body-content table').forEach(tbl => {
        if (!tbl.parentElement.classList.contains('table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            tbl.parentNode.insertBefore(wrapper, tbl);
            wrapper.appendChild(tbl);
        }
    });

    // 8. Multi-Language Switcher Controller (Indian Regional Languages)
    const langContainer = document.getElementById('langDropdownContainer');
    const langToggleBtn = document.getElementById('langToggleBtn');
    const langMenu = document.getElementById('langDropdownMenu');
    const langLabel = document.getElementById('currentLangLabel');
    const langOptions = document.querySelectorAll('.lang-option-btn');
    const mobileLangSelect = document.getElementById('mobileLangSelect');

    const langNames = {
        'en': 'English',
        'hi': 'हिंदी',
        'ta': 'தமிழ்',
        'te': 'తెలుగు',
        'ur': 'اردو',
        'bn': 'বাংলা',
        'mr': 'मराठी',
        'gu': 'ગુજરાતી',
        'pa': 'ਪੰਜਾਬੀ',
        'kn': 'ಕನ್ನಡ',
        'ml': 'മലയാളം',
        'or': 'ଓଡ଼ିଆ'
    };

    function getCookie(name) {
        const v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
        return v ? v[2] : null;
    }

    function setCookie(name, value, days) {
        const d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + "=" + value + ";path=/;expires=" + d.toUTCString();
    }

    function getCurrentLanguage() {
        const c = getCookie('googtrans');
        if (c) {
            const parts = c.split('/');
            if (parts.length >= 3 && parts[2]) {
                return parts[2];
            }
        }
        return 'en';
    }

    function setSiteLanguage(langCode) {
        if (!langCode || langCode === 'en') {
            setCookie('googtrans', '/en/en', 30);
            document.cookie = "googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
        } else {
            setCookie('googtrans', '/en/' + langCode, 30);
        }

        updateLanguageUI(langCode);

        const teCombo = document.querySelector('.goog-te-combo');
        if (teCombo) {
            teCombo.value = langCode === 'en' ? '' : langCode;
            teCombo.dispatchEvent(new Event('change'));
        } else {
            window.location.reload();
        }
    }

    function updateLanguageUI(langCode) {
        const displayName = langNames[langCode] || 'English';
        if (langLabel) {
            langLabel.textContent = displayName;
        }

        langOptions.forEach(btn => {
            if (btn.dataset.lang === langCode) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        if (mobileLangSelect) {
            mobileLangSelect.value = langCode;
        }
    }

    if (langToggleBtn && langContainer) {
        langToggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = langContainer.classList.toggle('open');
            langToggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (langMenu) {
                langMenu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            }
        });

        document.addEventListener('click', (e) => {
            if (!langContainer.contains(e.target)) {
                langContainer.classList.remove('open');
                langToggleBtn.setAttribute('aria-expanded', 'false');
                if (langMenu) {
                    langMenu.setAttribute('aria-hidden', 'true');
                }
            }
        });
    }

    langOptions.forEach(btn => {
        btn.addEventListener('click', () => {
            const lang = btn.dataset.lang;
            if (langContainer) {
                langContainer.classList.remove('open');
            }
            setSiteLanguage(lang);
        });
    });

    if (mobileLangSelect) {
        mobileLangSelect.addEventListener('change', () => {
            setSiteLanguage(mobileLangSelect.value);
        });
    }

    // Initialize current active language on page load
    const activeLang = getCurrentLanguage();
    updateLanguageUI(activeLang);

    // Suppress Google Translate default top banner iframe
    const cleanGoogleBanner = () => {
        document.querySelectorAll('.goog-te-banner-frame, iframe.skiptranslate, .VIpgJd-ZVi9od-aZ2wEe-wOHMyf, .VIpgJd-ZVi9od-ORHb-OEVmcb').forEach(el => {
            el.style.setProperty('display', 'none', 'important');
            el.style.setProperty('visibility', 'hidden', 'important');
            el.style.setProperty('height', '0', 'important');
            el.style.setProperty('width', '0', 'important');
            el.style.setProperty('opacity', '0', 'important');
        });
        if (document.body.style.top !== '0px') {
            document.body.style.setProperty('top', '0px', 'important');
        }
        if (document.documentElement.style.marginTop !== '0px') {
            document.documentElement.style.setProperty('margin-top', '0px', 'important');
        }
    };

    window.addEventListener('load', cleanGoogleBanner);
    setInterval(cleanGoogleBanner, 300);

    /* ==========================================================================
       Interactive Web Speech Synthesis Audio Article Player
       ========================================================================== */
    const audioPlayer = document.getElementById('audioArticlePlayer');
    if (audioPlayer && 'speechSynthesis' in window) {
        const playBtn = document.getElementById('audioPlayBtn');
        const playIcon = document.getElementById('audioPlayIcon');
        const pauseIcon = document.getElementById('audioPauseIcon');
        const stopBtn = document.getElementById('audioStopBtn');
        const speedBtn = document.getElementById('audioSpeedBtn');
        const statusLabel = document.getElementById('audioPlayerStatus');
        const langBadge = document.getElementById('audioLangBadge');
        const progressBar = document.getElementById('audioProgressBar');
        const progressTrack = document.getElementById('audioProgressTrack');

        const articleHeadline = document.querySelector('.article-headline');
        const articleExcerpt = document.querySelector('.article-lead-excerpt');
        const articleBody = document.querySelector('.article-body-content');

        let isPlaying = false;
        let isPaused = false;
        let playbackRate = 1.0;
        const speedOptions = [1.0, 1.25, 1.5, 2.0];
        let speedIndex = 0;
        let textChunks = [];
        let currentChunkIndex = 0;
        let activeUtterance = null;
        let chromeHeartbeatTimer = null;

        // Language to BCP 47 code map
        const langCodeMap = {
            'en': 'en-IN',
            'hi': 'hi-IN',
            'ta': 'ta-IN',
            'te': 'te-IN',
            'bn': 'bn-IN',
            'mr': 'mr-IN',
            'gu': 'gu-IN',
            'ur': 'ur-IN',
            'pa': 'pa-IN',
            'kn': 'kn-IN',
            'ml': 'ml-IN',
            'or': 'or-IN'
        };

        const langNameMap = {
            'en': 'English Voice',
            'hi': 'Hindi Voice / हिंदी',
            'ta': 'Tamil Voice / தமிழ்',
            'te': 'Telugu Voice / తెలుగు',
            'bn': 'Bengali Voice / বাংলা',
            'mr': 'Marathi Voice / मराठी',
            'gu': 'Gujarati Voice / ગુજરાતી',
            'ur': 'Urdu Voice / اردو',
            'pa': 'Punjabi Voice / ਪੰਜਾਬੀ',
            'kn': 'Kannada Voice / ಕನ್ನಡ',
            'ml': 'Malayalam Voice / മലയാളം',
            'or': 'Odia Voice / ଓଡ଼ିଆ'
        };

        // Extract clean text representation
        function getReadableArticleText() {
            let text = '';
            if (articleHeadline) text += articleHeadline.innerText.trim() + '. ';
            if (articleExcerpt) text += articleExcerpt.innerText.trim() + '. ';
            if (articleBody) {
                const clone = articleBody.cloneNode(true);
                clone.querySelectorAll('table, script, style, .table-responsive, .source-verification-box').forEach(el => el.remove());
                text += clone.innerText.replace(/\s+/g, ' ').trim();
            }
            return text;
        }

        // Split text into natural sentence chunks (max 180 chars) for Chrome compatibility
        function chunkTextIntoSentences(text) {
            if (!text) return [];
            const rawSentences = text.split(/(?<=[.!?।\n])\s+/);
            const chunks = [];
            
            rawSentences.forEach(s => {
                const trimmed = s.trim();
                if (!trimmed) return;
                
                if (trimmed.length > 180) {
                    const clauses = trimmed.split(/(?<=[,;])\s+/);
                    clauses.forEach(c => {
                        const cTrim = c.trim();
                        if (cTrim) chunks.push(cTrim);
                    });
                } else {
                    chunks.push(trimmed);
                }
            });
            return chunks.length > 0 ? chunks : [text.trim()];
        }

        function updateBadgeForCurrentLang() {
            const currentLang = getCurrentLanguage() || 'en';
            if (langBadge) {
                langBadge.textContent = langNameMap[currentLang] || 'Audio Reader';
            }
        }

        updateBadgeForCurrentLang();

        function getBestVoiceForLang(targetLangCode) {
            const voices = window.speechSynthesis.getVoices();
            if (!voices || voices.length === 0) return null;
            const prefix = targetLangCode.split('-')[0].toLowerCase();
            // 1. Exact match (e.g. hi-IN)
            let voice = voices.find(v => v.lang && v.lang.replace('_', '-').toLowerCase() === targetLangCode.toLowerCase());
            // 2. Prefix match (e.g. hi)
            if (!voice) {
                voice = voices.find(v => v.lang && v.lang.toLowerCase().startsWith(prefix));
            }
            // 3. Default voice
            if (!voice) {
                voice = voices.find(v => v.default) || voices[0];
            }
            return voice || null;
        }

        function speakNextChunk() {
            if (!isPlaying || isPaused) return;

            if (currentChunkIndex >= textChunks.length) {
                resetPlayerState('Listening Finished');
                return;
            }

            const chunk = textChunks[currentChunkIndex];
            const currentLang = getCurrentLanguage() || 'en';
            const targetLangCode = langCodeMap[currentLang] || navigator.language || 'en-US';

            activeUtterance = new SpeechSynthesisUtterance(chunk);
            activeUtterance.rate = playbackRate;
            activeUtterance.lang = targetLangCode;

            const selectedVoice = getBestVoiceForLang(targetLangCode);
            if (selectedVoice) {
                activeUtterance.voice = selectedVoice;
            }

            activeUtterance.onstart = () => {
                if (statusLabel) statusLabel.textContent = 'Playing Article Audio...';
                if (progressBar && textChunks.length > 0) {
                    const percent = Math.min(100, Math.round(((currentChunkIndex + 1) / textChunks.length) * 100));
                    progressBar.style.width = percent + '%';
                    if (progressTrack) progressTrack.setAttribute('aria-valuenow', percent);
                }
            };

            activeUtterance.onend = () => {
                if (isPlaying && !isPaused) {
                    currentChunkIndex++;
                    speakNextChunk();
                }
            };

            activeUtterance.onerror = (e) => {
                console.warn('Speech chunk notice:', e);
                if (isPlaying && !isPaused && e.error !== 'canceled' && e.error !== 'interrupted') {
                    currentChunkIndex++;
                    speakNextChunk();
                }
            };

            window._activeSarkariUtterance = activeUtterance;
            window.speechSynthesis.speak(activeUtterance);
        }

        function startPlayback() {
            window.speechSynthesis.cancel();
            window.speechSynthesis.resume();

            const fullText = getReadableArticleText();
            if (!fullText) return;

            textChunks = chunkTextIntoSentences(fullText);
            currentChunkIndex = 0;
            isPlaying = true;
            isPaused = false;

            audioPlayer.classList.add('is-playing');
            if (playIcon) playIcon.style.display = 'none';
            if (pauseIcon) pauseIcon.style.display = 'inline-block';
            if (stopBtn) stopBtn.style.display = 'inline-flex';
            if (statusLabel) statusLabel.textContent = 'Playing Article Audio...';
            updateBadgeForCurrentLang();

            if (chromeHeartbeatTimer) clearInterval(chromeHeartbeatTimer);
            chromeHeartbeatTimer = setInterval(() => {
                if (isPlaying && !isPaused && window.speechSynthesis.speaking) {
                    window.speechSynthesis.pause();
                    window.speechSynthesis.resume();
                }
            }, 10000);

            speakNextChunk();
        }

        function resetPlayerState(label = 'Listen to this Article') {
            isPlaying = false;
            isPaused = false;
            currentChunkIndex = 0;
            textChunks = [];
            window._activeSarkariUtterance = null;
            if (chromeHeartbeatTimer) {
                clearInterval(chromeHeartbeatTimer);
                chromeHeartbeatTimer = null;
            }
            audioPlayer.classList.remove('is-playing');
            if (playIcon) playIcon.style.display = 'inline-block';
            if (pauseIcon) pauseIcon.style.display = 'none';
            if (stopBtn) stopBtn.style.display = 'none';
            if (progressBar) progressBar.style.width = '0%';
            if (statusLabel) statusLabel.textContent = label;
        }

        if (playBtn) {
            playBtn.addEventListener('click', (e) => {
                if (e) e.preventDefault();
                window.speechSynthesis.resume();

                if (!isPlaying) {
                    startPlayback();
                } else if (isPaused) {
                    window.speechSynthesis.resume();
                    isPaused = false;
                    audioPlayer.classList.add('is-playing');
                    if (playIcon) playIcon.style.display = 'none';
                    if (pauseIcon) pauseIcon.style.display = 'inline-block';
                    if (statusLabel) statusLabel.textContent = 'Playing Article Audio...';
                } else {
                    window.speechSynthesis.pause();
                    isPaused = true;
                    audioPlayer.classList.remove('is-playing');
                    if (playIcon) playIcon.style.display = 'inline-block';
                    if (pauseIcon) pauseIcon.style.display = 'none';
                    if (statusLabel) statusLabel.textContent = 'Paused';
                }
            });
        }

        if (stopBtn) {
            stopBtn.addEventListener('click', (e) => {
                if (e) e.preventDefault();
                window.speechSynthesis.cancel();
                resetPlayerState('Stopped');
            });
        }

        if (speedBtn) {
            speedBtn.addEventListener('click', () => {
                speedIndex = (speedIndex + 1) % speedOptions.length;
                playbackRate = speedOptions[speedIndex];
                speedBtn.textContent = playbackRate.toFixed(1) + 'x';
                if (isPlaying && !isPaused) {
                    window.speechSynthesis.cancel();
                    speakNextChunk();
                }
            });
        }

        if (window.speechSynthesis.onvoiceschanged !== undefined) {
            window.speechSynthesis.onvoiceschanged = () => {
                updateBadgeForCurrentLang();
            };
        }
    }
});


