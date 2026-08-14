<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Documentation/SharedFaqContent.php';

use OneId\App\Documentation\SharedFaqContent;

/** @return array{requested_locale:string,effective_locale:string,fallback_used:bool,fallback_notice:?string,entries:array<int,array{id:string,question:string,answer:string}>} */
function oneid_shared_faq(?string $locale = null): array
{
    return (new SharedFaqContent())->resolve($locale ?? oneid_current_locale());
}

function oneid_render_login_faq(): string
{
    $faq = oneid_shared_faq();
    ob_start();
    ?>
    <div class="accordion" id="faqAccordion" lang="<?=htmlspecialchars($faq['effective_locale'], ENT_QUOTES, 'UTF-8')?>">
      <?php if ($faq['fallback_used']): ?>
        <div class="alert alert-warning" role="status"><?=htmlspecialchars((string) $faq['fallback_notice'], ENT_QUOTES, 'UTF-8')?></div>
      <?php endif; ?>
      <?php foreach ($faq['entries'] as $index => $entry): $number = $index + 1; ?>
        <div class="accordion-item" data-faq-id="<?=htmlspecialchars($entry['id'], ENT_QUOTES, 'UTF-8')?>">
          <h2 class="accordion-header" id="faq-login-heading-<?=$number?>">
            <button class="accordion-button<?=$index === 0 ? '' : ' collapsed'?>" type="button"
              data-bs-toggle="collapse" data-bs-target="#faq-login-collapse-<?=$number?>"
              aria-expanded="<?=$index === 0 ? 'true' : 'false'?>" aria-controls="faq-login-collapse-<?=$number?>">
              <?=htmlspecialchars($entry['question'], ENT_QUOTES, 'UTF-8')?>
            </button>
          </h2>
          <div id="faq-login-collapse-<?=$number?>" class="accordion-collapse collapse<?=$index === 0 ? ' show' : ''?>"
            data-bs-parent="#faqAccordion" aria-labelledby="faq-login-heading-<?=$number?>">
            <div class="accordion-body"><?=htmlspecialchars($entry['answer'], ENT_QUOTES, 'UTF-8')?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

function oneid_render_dashboard_faq(): string
{
    $faq = oneid_shared_faq();
    ob_start();
    ?>
    <div class="panel-group" id="faqAccordion" role="tablist" aria-multiselectable="false"
      lang="<?=htmlspecialchars($faq['effective_locale'], ENT_QUOTES, 'UTF-8')?>">
      <?php if ($faq['fallback_used']): ?>
        <div class="alert alert-warning" role="status"><?=htmlspecialchars((string) $faq['fallback_notice'], ENT_QUOTES, 'UTF-8')?></div>
      <?php endif; ?>
      <?php foreach ($faq['entries'] as $index => $entry): $number = $index + 1; ?>
        <div class="panel panel-default oneid-faq-item" data-faq-id="<?=htmlspecialchars($entry['id'], ENT_QUOTES, 'UTF-8')?>">
          <div class="panel-heading" role="tab" id="faq-dashboard-heading-<?=$number?>">
            <h6 class="panel-title">
              <a class="<?=$index === 0 ? '' : 'collapsed'?>" role="button" data-toggle="collapse"
                data-parent="#faqAccordion" href="#faq-dashboard-collapse-<?=$number?>"
                aria-expanded="<?=$index === 0 ? 'true' : 'false'?>" aria-controls="faq-dashboard-collapse-<?=$number?>">
                <span class="oneid-faq-number"><?=str_pad((string) $number, 2, '0', STR_PAD_LEFT)?></span><span class="oneid-faq-question"><?=htmlspecialchars($entry['question'], ENT_QUOTES, 'UTF-8')?></span><i class="fa fa-chevron-down oneid-faq-chevron" aria-hidden="true"></i>
              </a>
            </h6>
          </div>
          <div id="faq-dashboard-collapse-<?=$number?>" class="panel-collapse collapse<?=$index === 0 ? ' in' : ''?>"
            role="tabpanel" aria-labelledby="faq-dashboard-heading-<?=$number?>">
            <div class="panel-body"><span class="oneid-faq-answer-icon"><i class="fa fa-info-circle" aria-hidden="true"></i></span><p><?=htmlspecialchars($entry['answer'], ENT_QUOTES, 'UTF-8')?></p></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}
