<?php
/**
 * Interactive Audio Article Reader Component
 * Uses Web Speech Synthesis API to read verified article summaries and full content
 * across 12 Indian regional languages with live playback controls.
 */
?>
<div class="audio-article-player" id="audioArticlePlayer" role="region" aria-label="Listen to Article">
    <div class="audio-player-inner">
        
        <!-- Left: Play/Pause Button -->
        <button type="button" class="audio-play-btn" id="audioPlayBtn" aria-label="Listen to this article">
            <span class="audio-icon-play" id="audioPlayIcon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><polygon points="6 3 20 12 6 21 6 3"></polygon></svg>
            </span>
            <span class="audio-icon-pause" id="audioPauseIcon" style="display:none;">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><rect x="6" y="4" width="4" height="16"></rect><rect x="14" y="4" width="4" height="16"></rect></svg>
            </span>
        </button>

        <!-- Center: Status & Visual Audio Waves -->
        <div class="audio-player-info">
            <div class="audio-player-top">
                <span class="audio-player-title" id="audioPlayerStatus">Listen to this Article</span>
                <span class="audio-player-tag" id="audioLangBadge">English / Voice AI</span>
            </div>
            
            <!-- Progress Bar / Waveforms -->
            <div class="audio-progress-container" id="audioProgressTrack" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                <div class="audio-progress-bar" id="audioProgressBar" style="width: 0%;"></div>
            </div>
        </div>

        <!-- Right: Speed Selector & Stop Button -->
        <div class="audio-player-controls">
            <button type="button" class="audio-speed-btn" id="audioSpeedBtn" title="Change Playback Speed">
                1.0x
            </button>
            <button type="button" class="audio-stop-btn" id="audioStopBtn" title="Stop Audio" style="display:none;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><rect x="6" y="6" width="12" height="12"></rect></svg>
            </button>
        </div>

    </div>
</div>
