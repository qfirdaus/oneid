(function(window,document){
  'use strict';
  var config=window.OneIdProductTourConfig||null;
  if(!config||!config.enabled){return;}
  var storageKey='oneid.product-tour.'+config.id;
  var layer=null,card=null,focus=null,index=0,active=false,lastFocused=null,resizeTimer=null,scrollTimer=null,resolvedSteps=[],currentStep=null,currentTarget=null;

  function visible(element){
    if(!element){return false;}
    var style=window.getComputedStyle(element),rect=element.getBoundingClientRect();
    return style.display!=='none'&&style.visibility!=='hidden'&&rect.width>0&&rect.height>0;
  }
  function visibleTarget(selector){
    var matches=document.querySelectorAll(selector);
    for(var i=0;i<matches.length;i++){if(visible(matches[i])){return matches[i];}}
    return null;
  }
  function mobile(){return window.matchMedia('(max-width: 767px)').matches;}
  function availableSteps(){
    return (config.steps||[]).filter(function(step){
      return !!visibleTarget(step.selector)||(mobile()&&step.mobileReveal==='sidebar'&&!!document.querySelector(step.selector));
    });
  }
  function revealTarget(step){
    var target=visibleTarget(step.selector);
    if(target||!mobile()||step.mobileReveal!=='sidebar'){return target;}
    var toggle=document.querySelector('.oneid-mobile-menu-toggle');
    if(toggle&&toggle.getAttribute('aria-expanded')!=='true'){toggle.click();}
    return visibleTarget(step.selector);
  }
  function dialogsOpen(){
    return Array.prototype.some.call(document.querySelectorAll('.sweet-alert.showSweetAlert,.modal.in,.modal.show'),visible);
  }
  function rememberLocal(status){try{window.localStorage.setItem(storageKey,status);}catch(ignored){}}
  function remembered(){try{return window.localStorage.getItem(storageKey)||'';}catch(ignored){return '';}}
  function remember(status){
    rememberLocal(status);
    if(!config.persistenceEnabled||!config.apiUrl||!config.csrfToken){return;}
    var body=new URLSearchParams({_csrf_token:config.csrfToken,user_set_product_tour_status:'1',tour_id:config.tourId,tour_version:String(config.tourVersion),completion_status:status});
    window.fetch(config.apiUrl,{method:'POST',credentials:'same-origin',keepalive:true,headers:{'X-CSRF-Token':config.csrfToken,'Accept':'application/json'},body:body})
      .then(function(response){if(!response.ok){throw new Error('tour progress was not saved');}return response.json();})
      .catch(function(){/* Local completion remains the safe fallback. */});
  }
  function alreadyCompleted(){return config.serverStatus==='completed'||config.serverStatus==='skipped'||remembered()!=='';}
  function escapeText(value){return String(value==null?'':value);}
  function build(){
    layer=document.createElement('div');layer.className='oneid-tour-layer';layer.setAttribute('role','presentation');
    focus=document.createElement('div');focus.className='oneid-tour-focus';focus.setAttribute('aria-hidden','true');layer.appendChild(focus);
    card=document.createElement('section');card.className='oneid-tour-card';card.setAttribute('role','dialog');card.setAttribute('aria-modal','true');card.setAttribute('aria-labelledby','oneid-tour-title');layer.appendChild(card);
    document.body.appendChild(layer);
  }
  function setRect(node,left,top,width,height){node.style.left=left+'px';node.style.top=top+'px';node.style.width=Math.max(0,width)+'px';node.style.height=Math.max(0,height)+'px';}
  function position(){
    if(!active){return;}
    if(!currentStep||!visible(currentTarget)){return;}
    var target=currentTarget;
    var targetStyle=window.getComputedStyle(target),rect=target.getBoundingClientRect(),pad=7,left=Math.max(6,rect.left-pad),top=Math.max(6,rect.top-pad),right=Math.min(window.innerWidth-6,rect.right+pad),bottom=Math.min(window.innerHeight-6,rect.bottom+pad);
    var cornerRadius=function(value){var parsed=parseFloat(value);return (Number.isFinite(parsed)?Math.max(5,parsed+pad):12)+'px';};
    focus.style.borderRadius=[cornerRadius(targetStyle.borderTopLeftRadius),cornerRadius(targetStyle.borderTopRightRadius),cornerRadius(targetStyle.borderBottomRightRadius),cornerRadius(targetStyle.borderBottomLeftRadius)].join(' ');
    setRect(focus,left,top,right-left,bottom-top);
    if(!mobile()){
      var cardRect=card.getBoundingClientRect(),gap=14,cardLeft=Math.max(12,Math.min(window.innerWidth-cardRect.width-12,left));
      var below=bottom+gap,cardTop=below+cardRect.height<=window.innerHeight-12?below:Math.max(12,top-cardRect.height-gap);
      card.style.left=cardLeft+'px';card.style.top=cardTop+'px';card.style.bottom='auto';
    }
  }
  function render(step,total){
    card.innerHTML='';
    var eyebrow=document.createElement('span');eyebrow.className='oneid-tour-eyebrow';eyebrow.textContent=escapeText(config.text.eyebrow);
    var title=document.createElement('h2');title.id='oneid-tour-title';title.textContent=escapeText(step.title);
    var body=document.createElement('p');body.textContent=escapeText(step.body);
    var progressRow=document.createElement('div');progressRow.className='oneid-tour-progress-row';
    var progress=document.createElement('span');progress.className='oneid-tour-progress';progress.textContent=escapeText(config.text.step).replace('{current}',String(index+1)).replace('{total}',String(total));
    var progressTrack=document.createElement('span');progressTrack.className='oneid-tour-progress-track';progressTrack.setAttribute('aria-hidden','true');
    var progressValue=document.createElement('span');progressValue.className='oneid-tour-progress-value';progressValue.style.width=((index+1)/total*100)+'%';progressTrack.appendChild(progressValue);progressRow.appendChild(progress);progressRow.appendChild(progressTrack);
    var actions=document.createElement('div');actions.className='oneid-tour-actions';
    var skip=document.createElement('button');skip.type='button';skip.className='oneid-tour-skip';skip.textContent=escapeText(config.text.skip);skip.addEventListener('click',function(){finish('skipped');});
    var back=document.createElement('button');back.type='button';back.className='oneid-tour-back';back.textContent=escapeText(config.text.back);back.disabled=index===0;back.addEventListener('click',function(){show(index-1,-1);});
    var next=document.createElement('button');next.type='button';next.className='oneid-tour-next';next.textContent=escapeText(index===total-1?config.text.finish:config.text.next);next.addEventListener('click',function(){if(index===total-1){finish('completed');}else{show(index+1,1);}});
    actions.appendChild(skip);actions.appendChild(back);actions.appendChild(next);
    card.appendChild(eyebrow);card.appendChild(title);card.appendChild(body);card.appendChild(progressRow);card.appendChild(actions);
    window.setTimeout(function(){next.focus();position();},0);
  }
  function moveTargetIntoView(target){
    if(window.getComputedStyle(target).position==='fixed'){return;}
    var behavior=window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth';
    if(!mobile()){target.scrollIntoView({behavior:behavior,block:'center',inline:'nearest'});return;}
    var rect=target.getBoundingClientRect(),cardHeight=Math.max(190,card.getBoundingClientRect().height),visibleBottom=Math.max(110,window.innerHeight-cardHeight-32),desiredTop=Math.max(12,(visibleBottom-Math.min(rect.height,visibleBottom-24))/2);
    window.scrollBy({top:rect.top-desiredTop,left:0,behavior:behavior});
  }
  function show(nextIndex,direction){
    if(!resolvedSteps.length){finish('skipped');return;}
    var candidate=Math.max(0,Math.min(nextIndex,resolvedSteps.length-1)),target=null,step=null,delta=direction===-1?-1:1;
    while(candidate>=0&&candidate<resolvedSteps.length){step=resolvedSteps[candidate];target=revealTarget(step);if(target){break;}candidate+=delta;}
    if(!target){finish('skipped');return;}
    index=candidate;currentStep=step;currentTarget=target;
    render(step,resolvedSteps.length);
    window.setTimeout(function(){moveTargetIntoView(target);window.setTimeout(position,mobile()?320:260);},0);
  }
  function start(manual){
    if(active||dialogsOpen()){if(!manual){window.setTimeout(function(){start(false);},1200);}return;}
    if(!manual&&alreadyCompleted()){return;}
    resolvedSteps=availableSteps();if(!resolvedSteps.length){if(!manual){window.setTimeout(function(){start(false);},1200);}return;}
    active=true;lastFocused=document.activeElement;build();show(0,1);document.documentElement.classList.add('oneid-tour-active');
  }
  function finish(status){
    if(!active){return;}active=false;remember(status);window.clearTimeout(resizeTimer);window.clearTimeout(scrollTimer);resolvedSteps=[];currentStep=null;currentTarget=null;
    if(layer&&layer.parentNode){layer.parentNode.removeChild(layer);}layer=null;card=null;focus=null;document.documentElement.classList.remove('oneid-tour-active');
    if(lastFocused&&typeof lastFocused.focus==='function'){lastFocused.focus();}
  }
  document.addEventListener('click',function(event){
    var trigger=event.target.closest?event.target.closest('[data-oneid-product-tour-start]'):null;
    if(trigger){event.preventDefault();start(true);}
  });
  document.addEventListener('keydown',function(event){
    if(!active){return;}
    if(event.key==='Escape'){event.preventDefault();finish('skipped');}
    if(event.key==='Tab'&&card){var buttons=card.querySelectorAll('button:not(:disabled)');if(!buttons.length){return;}var first=buttons[0],last=buttons[buttons.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}}
  });
  window.addEventListener('resize',function(){window.clearTimeout(resizeTimer);resizeTimer=window.setTimeout(position,120);});
  window.addEventListener('scroll',function(){if(!active){return;}window.clearTimeout(scrollTimer);scrollTimer=window.setTimeout(position,40);},{passive:true});
  window.addEventListener('orientationchange',function(){window.setTimeout(position,250);});
  document.addEventListener('visibilitychange',function(){if(active&&!document.hidden){window.setTimeout(position,100);}});
  var localStatus=remembered();
  if(config.persistenceEnabled&&!config.serverStatus&&(localStatus==='completed'||localStatus==='skipped')){remember(localStatus);}
  if(config.serverStatus){rememberLocal(config.serverStatus);}
  window.setTimeout(function(){start(false);},1800);
  window.OneIdProductTour={start:function(){start(true);},reset:function(){try{window.localStorage.removeItem(storageKey);}catch(ignored){}}};
})(window,document);
