<?php
declare(strict_types=1);

function oneid_render_display_settings(): void
{
    $h = static fn (string $key): string => htmlspecialchars(
        oneid_translate($key),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
    ?>
    <div class="oneid-display-settings" data-oneid-display-settings>
        <button class="oneid-display-settings__trigger" type="button"
                aria-expanded="false" aria-controls="oneid-display-settings-panel"
                aria-label="<?=$h('display_settings.open')?>" title="<?=$h('display_settings.open')?>">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.09A1.7 1.7 0 0 0 8.6 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3v-4h.09A1.7 1.7 0 0 0 4.6 8.6a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.09A1.7 1.7 0 0 0 15.4 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.1.38.31.73.6 1 .3.27.69.41 1.1.4H21v4h-.09a1.7 1.7 0 0 0-1.51.6Z"></path></svg>
        </button>
        <section class="oneid-display-settings__panel" id="oneid-display-settings-panel"
                 role="dialog" aria-modal="false" aria-labelledby="oneid-display-settings-title" hidden>
            <header class="oneid-display-settings__header">
                <div>
                    <span class="oneid-display-settings__eyebrow"><?=$h('display_settings.eyebrow')?></span>
                    <h2 id="oneid-display-settings-title"><?=$h('display_settings.title')?></h2>
                </div>
                <button class="oneid-display-settings__close" type="button"
                        aria-label="<?=$h('common.close')?>"><span aria-hidden="true">×</span></button>
            </header>
            <div class="oneid-display-settings__body">
                <fieldset class="oneid-display-settings__group">
                    <legend><?=$h('display_settings.text_size')?></legend>
                    <p id="oneid-display-settings-size-help"><?=$h('display_settings.text_size_help')?></p>
                    <div class="oneid-display-settings__sizes" role="group"
                         aria-describedby="oneid-display-settings-size-help">
                        <button type="button" data-oneid-font-scale="100" aria-pressed="true">100%</button>
                        <button type="button" data-oneid-font-scale="108" aria-pressed="false">108%</button>
                        <button type="button" data-oneid-font-scale="115" aria-pressed="false">115%</button>
                        <button type="button" data-oneid-font-scale="123" aria-pressed="false">123%</button>
                        <button type="button" data-oneid-font-scale="130" aria-pressed="false">130%</button>
                    </div>
                </fieldset>
                <div class="oneid-display-settings__toggles">
                    <label><span><strong><?=$h('display_settings.high_contrast')?></strong><small><?=$h('display_settings.high_contrast_help')?></small></span><input type="checkbox" data-oneid-setting="highContrast"></label>
                    <label><span><strong><?=$h('display_settings.reduce_motion')?></strong><small><?=$h('display_settings.reduce_motion_help')?></small></span><input type="checkbox" data-oneid-setting="reduceMotion"></label>
                    <label><span><strong><?=$h('display_settings.underline_links')?></strong><small><?=$h('display_settings.underline_links_help')?></small></span><input type="checkbox" data-oneid-setting="underlineLinks"></label>
                </div>
            </div>
            <footer class="oneid-display-settings__footer">
                <button type="button" class="oneid-display-settings__reset" data-oneid-display-reset><?=$h('display_settings.reset')?></button>
                <span class="oneid-display-settings__status" role="status" aria-live="polite"></span>
            </footer>
        </section>
    </div>
    <?php
}
