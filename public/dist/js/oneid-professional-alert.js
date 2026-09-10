(function(window,document){
  'use strict';

  var alertSelector='.sweet-alert';
  var visibleClass='showSweetAlert';
  var lastVisible=false;

  function localeIsMalay(){
    return String(document.documentElement.lang||'').toLowerCase().indexOf('ms')===0;
  }

  function alertKind(alert){
    var icon=alert.querySelector('.sa-icon');
    if(!icon){return 'notice';}
    if(icon.classList.contains('sa-error')){return 'error';}
    if(icon.classList.contains('sa-warning')){return 'warning';}
    if(icon.classList.contains('sa-success')){return 'success';}
    if(icon.classList.contains('sa-info')){return 'info';}
    return 'notice';
  }

  function eyebrow(kind){
    var malay=localeIsMalay();
    if(kind==='error'){return malay?'NOTIS SISTEM ONEID':'ONEID SYSTEM NOTICE';}
    if(kind==='warning'){return malay?'PENGESAHAN TINDAKAN ONEID':'ONEID ACTION CONFIRMATION';}
    if(kind==='success'){return malay?'TINDAKAN ONEID SELESAI':'ONEID ACTION COMPLETED';}
    return malay?'PEMBERITAHUAN ONEID':'ONEID NOTIFICATION';
  }

  function apply(alert,options){
    if(!alert||!alert.classList.contains(visibleClass)){return;}
    var kind=(options&&options.kind)||alertKind(alert);
    var kinds=['notice','info','success','warning','error'];
    alert.classList.add('oneid-professional-alert');
    kinds.forEach(function(item){
      var className='oneid-professional-alert--'+item;
      if(item===kind){alert.classList.add(className);}
      else{alert.classList.remove(className);}
    });
    var overlay=document.querySelector('.sweet-overlay');
    if(overlay){overlay.classList.add('oneid-professional-alert-overlay');}
    var customLabel=alert.querySelector('.oneid-site-api-eyebrow,.oneid-session-eyebrow,.oneid-user-session-eyebrow');
    var label=alert.querySelector('.oneid-professional-alert__eyebrow');
    if(customLabel&&label){label.parentNode.removeChild(label);label=null;}
    if(!customLabel&&!label){
      label=document.createElement('div');
      label.className='oneid-professional-alert__eyebrow';
      var icon=alert.querySelector('.sa-icon');
      alert.insertBefore(label,icon||alert.firstChild);
    }
    if(label){label.textContent=(options&&options.eyebrow)||eyebrow(kind);}
  }

  function sync(){
    var alert=document.querySelector(alertSelector);
    var overlay=document.querySelector('.sweet-overlay');
    var visible=!!(alert&&alert.classList.contains(visibleClass));
    if(visible){
      var kind=alertKind(alert);
      var needsTheme=!lastVisible||!alert.classList.contains('oneid-professional-alert')||!alert.classList.contains('oneid-professional-alert--'+kind);
      lastVisible=true;
      if(needsTheme){apply(alert,{kind:kind});}
      return;
    }
    lastVisible=false;
    if(overlay){overlay.classList.remove('oneid-professional-alert-overlay');}
  }

  window.OneIdProfessionalAlert={apply:apply,sync:sync};
  if(window.MutationObserver){
    var alertObserver=null;
    var bootstrapObserver=null;
    var observeAlert=function(){
      var alert=document.querySelector(alertSelector);
      if(!alert||alertObserver){return false;}
      alertObserver=new MutationObserver(sync);
      alertObserver.observe(alert,{attributes:true,attributeFilter:['class']});
      if(bootstrapObserver){bootstrapObserver.disconnect();bootstrapObserver=null;}
      sync();
      return true;
    };
    if(!observeAlert()){
      bootstrapObserver=new MutationObserver(observeAlert);
      bootstrapObserver.observe(document.body||document.documentElement,{childList:true,subtree:true});
    }
  }
  document.addEventListener('DOMContentLoaded',sync);
})(window,document);
