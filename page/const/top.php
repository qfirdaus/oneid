<!-- Top Menu Items -->
			<nav class="navbar navbar-inverse navbar-fixed-top oneid-header-lightline">
				<div class="nav-wrap">
				<div class="mobile-only-brand pull-left">
					<div class="nav-header pull-left">
						<div class="logo-wrap">
							<a href="./dashboard">
								<img class="brand-img img-responsive" src="../img/logo_upnm_30.png" width="187" height="50" alt="Universiti Pertahanan Nasional Malaysia 30 Tahun"/>
								<span class="brand-text img-responsive"><img src="../img/logo_upnm_30.png" width="187" height="50" alt="Universiti Pertahanan Nasional Malaysia 30 Tahun"/></span>
							</a>
						</div>
					</div>	
				</div>	
				<div class="oneid-session-indicators oneid-session-indicators--user" aria-live="off">
					<button type="button" class="oneid-health-trigger" id="oneid_user_health_trigger" aria-expanded="false" aria-controls="oneid_user_health_panel" title="<?=htmlspecialchars(oneid_translate('dashboard.health.open'), ENT_QUOTES, 'UTF-8')?>" aria-label="<?=htmlspecialchars(oneid_translate('dashboard.health.open'), ENT_QUOTES, 'UTF-8')?>">
						<svg class="oneid-gauge-icon fa-gauge-high" viewBox="0 0 32 24" aria-hidden="true" focusable="false"><path class="oneid-gauge-icon__track" d="M4 19a12 12 0 0 1 24 0"/><path class="oneid-gauge-icon__green" d="M4 19a12 12 0 0 1 3.5-8.5"/><path class="oneid-gauge-icon__amber" d="M7.5 10.5A12 12 0 0 1 18 7.2"/><path class="oneid-gauge-icon__red" d="M18 7.2A12 12 0 0 1 28 19"/><path class="oneid-gauge-icon__needle" d="M16 19l7-7"/><circle class="oneid-gauge-icon__hub" cx="16" cy="19" r="2.2"/></svg>
					</button>
					<div class="oneid-session-indicator" id="oneid_user_session_indicator" hidden data-oneid-tooltip="<?=htmlspecialchars(oneid_translate('user_session.remaining_help'), ENT_QUOTES, 'UTF-8')?>">
						<i class="fa fa-clock-o" aria-hidden="true"></i><span><?=htmlspecialchars(oneid_translate('user_session.remaining_label'), ENT_QUOTES, 'UTF-8')?></span><strong id="oneid_user_session_remaining">--:--</strong><button type="button" class="oneid-session-renew-button" data-oneid-user-session-renew title="<?=htmlspecialchars(oneid_translate('user_session.renew_now'), ENT_QUOTES, 'UTF-8')?>" aria-label="<?=htmlspecialchars(oneid_translate('user_session.renew_now'), ENT_QUOTES, 'UTF-8')?>">+</button>
					</div>
				</div>
				</div>
			</nav>
			<!-- /Top Menu Items -->

<!-- Top Menu Items 
			<nav class="navbar navbar-inverse navbar-fixed-top banner-nav">
				<div class="nav-wrap">
				<div class="mobile-only-brand pull-left">
					<div class="nav-header pull-left">
						<div class="logo-wrap">
							<a href="./dashboard">
								<img class="brand-img img-responsive" src="../img/Banner_Atas_OneID.jpg" alt="brand"/>
								<span class="brand-text img-responsive"><img  src="../img/Banner_Atas_OneID.jpg" alt="brand"/></span>
							</a>
						</div>
					</div>	
				</div>
				</div>
			</nav>
			//Top Menu Items -->


			<!-- ../img/ -->
