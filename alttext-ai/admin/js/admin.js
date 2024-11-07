!function(){"use strict";const{__:e}=wp.i18n;function t(t,a=[]){return t?new Promise(((e,n)=>{jQuery.ajax({type:"post",dataType:"json",data:{action:"atai_single_generate",security:wp_atai.security_single_generate,attachment_id:t,keywords:a},url:wp_atai.ajax_url,success:function(t){e(t)},error:function(e){n(new Error("AJAX request failed"))}})})):Promise.reject(new Error(e("Attachment ID is missing","alttext-ai")))}function a(){jQuery.ajax({type:"post",dataType:"json",data:{action:"atai_bulk_generate",security:wp_atai.security_bulk_generate,posts_per_page:window.atai.postsPerPage,last_post_id:window.atai.lastPostId,keywords:window.atai.bulkGenerateKeywords,negativeKeywords:window.atai.bulkGenerateNegativeKeywords,mode:window.atai.bulkGenerateMode,onlyAttached:window.atai.bulkGenerateOnlyAttached,onlyNew:window.atai.bulkGenerateOnlyNew,wcProducts:window.atai.bulkGenerateWCProducts,wcOnlyFeatured:window.atai.bulkGenerateWCOnlyFeatured,batchId:window.atai.bulkGenerateBatchId},url:wp_atai.ajax_url,success:function(t){window.atai.progressCurrent+=t.process_count,window.atai.progressSuccessful+=t.success_count,window.atai.lastPostId=t.last_post_id,window.atai.progressBarEl.data("current",window.atai.progressCurrent),window.atai.progressLastPostId.text(window.atai.lastPostId),window.atai.progressCurrentEl.text(window.atai.progressCurrent),window.atai.progressSuccessfulEl.text(window.atai.progressSuccessful);const n=100*window.atai.progressCurrent/window.atai.progressMax;window.atai.progressBarEl.css("width",n+"%"),window.atai.progressPercent.text(n.toFixed(2)+"%"),t.recursive?a():(window.atai.progressButtonCancel.hide(),window.atai.progressBarWrapper.hide(),window.atai.progressButtonFinished.show(),window.atai.progressHeading.text(e("Update complete!","alttext-ai")),window.atai.redirectUrl=t?.redirect_url)},error:function(t){window.atai.progressButtonCancel.hide(),window.atai.progressBarWrapper.hide(),window.atai.progressButtonFinished.show(),window.atai.progressHeading.text(e("The update was stopped due to a server error. Restart the update to pick up where it left off.","alttext-ai"))}})}function n(e){return e.split(",").map((function(e){return e.trim()})).filter((function(e){return e.length>0})).slice(0,6)}function i(e){e=e.replace(/[[]/,"\\[").replace(/[\]]/,"\\]");let t=new RegExp("[\\?&]"+e+"=([^&#]*)").exec(window.location.search);return null===t?"":decodeURIComponent(t[1].replace(/\+/g," "))}function r(e,t,a){let n=document.getElementById(e);if(!n)return!1;let i=document.getElementById(t+"-"+a);if(i&&i.remove(),!window.location.href.includes("upload.php"))return!1;let r=s(t,a,"modal"),o=n.parentNode;return o&&o.replaceChild(r,n),!0}function s(a,i,r){const s=new URL(window.location.href);s.searchParams.set("atai_action","generate");const o=a+"-"+i,d=document.createElement("div");d.setAttribute("id",o),d.classList.add("description"),d.classList.add("atai-generate-button");const c=document.createElement("a");c.setAttribute("id",o+"-anchor"),c.setAttribute("href",s),c.className="button-secondary button-large atai-generate-button__anchor";const l=document.createElement("div");l.setAttribute("id",o+"-checkbox-wrapper"),l.classList.add("atai-generate-button__keywords-checkbox-wrapper");const u=document.createElement("input");u.setAttribute("type","checkbox"),u.setAttribute("id",o+"-keywords-checkbox"),u.setAttribute("name","atai-generate-button-keywords-checkbox"),u.className="atai-generate-button__keywords-checkbox";const p=document.createElement("label");p.htmlFor="atai-generate-button-keywords-checkbox",p.innerText="Add SEO keywords";const w=document.createElement("div");w.setAttribute("id",o+"-textfield-wrapper"),w.className="atai-generate-button__keywords-textfield-wrapper",w.style.display="none";const g=document.createElement("input");g.setAttribute("type","text"),g.setAttribute("id",o+"-textfield"),g.className="atai-generate-button__keywords-textfield",g.setAttribute("name","atai-generate-button-keywords"),g.size=40,l.appendChild(u),l.appendChild(p),w.appendChild(g),u.addEventListener("change",(function(){this.checked?(w.style.display="block",g.setSelectionRange(0,0),g.focus()):w.style.display="none"}));wp_atai.can_user_upload_files?(e=>{jQuery.ajax({type:"post",dataType:"json",data:{action:"atai_check_image_eligibility",security:wp_atai.security_check_attachment_eligibility,attachment_id:e},url:wp_atai.ajax_url,success:function(e){if("success"!==e.status){const e=document.querySelector(`#${o}-anchor`),t=document.querySelector(`#${o}-keywords-checkbox`);e?e.classList.add("disabled"):c.classList.add("disabled"),t?t.classList.add("disabled"):u.classList.add("disabled")}}})})(i):(c.classList.add("disabled"),u.disabled=!0),c.title=e("AltText.ai: Update alt text for this single image","alttext-ai"),c.onclick=function(){this.classList.add("disabled");let t=this.querySelector("span");t&&(t.innerText=e("Processing...","alttext-ai"))};const y=document.createElement("img");y.src=wp_atai.icon_button_generate,y.alt=e("Update Alt Text with AltText.ai","alttext-ai"),c.appendChild(y);const h=document.createElement("span");h.innerText=e("Update Alt Text","alttext-ai"),c.appendChild(h),d.appendChild(c),d.appendChild(l),d.appendChild(w);const _=document.createElement("span");return _.classList.add("atai-update-notice"),d.appendChild(_),c.addEventListener("click",(async function(a){a.preventDefault(),wp_atai.has_api_key||(window.location.href=wp_atai.settings_page_url+"&api_key_missing=1");const s="single"==r?document.getElementById("title"):document.querySelector('[data-setting="title"] input'),o="single"==r?document.getElementById("attachment_caption"):document.querySelector('[data-setting="caption"] textarea'),d="single"==r?document.getElementById("attachment_content"):document.querySelector('[data-setting="description"] textarea'),l="single"==r?document.getElementById("attachment_alt"):document.querySelector('[data-setting="alt"] textarea'),p=u.checked?n(g.value):[];_&&(_.innerText="",_.classList.remove("atai-update-notice--success","atai-update-notice--error"));const w=await t(i,p);if("success"===w.status)l.value=w.alt_text,"yes"===wp_atai.should_update_title&&(s.value=w.alt_text,"single"==r&&s.previousElementSibling.classList.add("screen-reader-text")),"yes"===wp_atai.should_update_caption&&(o.value=w.alt_text),"yes"===wp_atai.should_update_description&&(d.value=w.alt_text),_.innerText=e("Updated","alttext-ai"),_.classList.add("atai-update-notice--success"),setTimeout((()=>{_.classList.remove("atai-update-notice--success")}),3e3);else{let t=e("Unable to generate alt text. Check error logs for details.","alttext-ai");w?.message&&(t=w.message),_.innerText=t,_.classList.add("atai-update-notice--error")}c.classList.remove("disabled"),c.querySelector("span").innerText=e("Update Alt Text","alttext-ai")})),d}function o(e,t,a){if("button-click"===t&&!e.target.matches(".media-modal .right, .media-modal .left"))return;const n=new URLSearchParams(window.location.search).get("item");n&&r("alt-text-description",a,n)}function d(){const a=wp.media.view.Attachment.Details;wp.media.view.Attachment.Details=a.extend({ATAICheckboxToggle:function(e){const t=e.currentTarget,a=t.parentNode.nextElementSibling,n=a.querySelector(".atai-generate-button__keywords-textfield");t.checked?(a.style.display="block",n.setSelectionRange(0,0),n.focus()):a.style.display="none"},ATAIAnchorClick:async function(a){a.preventDefault();const i=this.model.id,r=a.currentTarget,s=r.closest(".attachment-details"),o=r.closest(".atai-generate-button"),d=o.querySelector(".atai-generate-button__keywords-checkbox"),c=o.querySelector(".atai-generate-button__keywords-textfield"),l=o.querySelector(".atai-update-notice");r.classList.add("disabled");const u=r.querySelector("span");u&&(u.innerText=e("Processing...","alttext-ai")),wp_atai.has_api_key||(window.location.href=wp_atai.settings_page_url+"&api_key_missing=1");const p=s.querySelector('[data-setting="title"] input'),w=s.querySelector('[data-setting="caption"] textarea'),g=s.querySelector('[data-setting="description"] textarea'),y=s.querySelector('[data-setting="alt"] textarea'),h=d.checked?n(c.value):[];l&&(l.innerText="",l.classList.remove("atai-update-notice--success","atai-update-notice--error"));const _=await t(i,h);if("success"===_.status)y.value=_.alt_text,"yes"===wp_atai.should_update_title&&(p.value=_.alt_text),"yes"===wp_atai.should_update_caption&&(w.value=_.alt_text),"yes"===wp_atai.should_update_description&&(g.value=_.alt_text),l.innerText=e("Updated","alttext-ai"),l.classList.add("atai-update-notice--success"),setTimeout((()=>{l.classList.remove("atai-update-notice--success")}),3e3);else{let t=e("Unable to generate alt text. Check error logs for details.","alttext-ai");_?.message&&(t=_.message),l.innerText=t,l.classList.add("atai-update-notice--error")}r.classList.remove("disabled"),u.innerText=e("Update Alt Text","alttext-ai")},events:{...a.prototype.events,"change .atai-generate-button__keywords-checkbox":"ATAICheckboxToggle","click .atai-generate-button__anchor":"ATAIAnchorClick"},template:function(e){const t=a.prototype.template.apply(this,arguments),n=document.createElement("div");n.innerHTML=t;const i=n.querySelector("p#alt-text-description");if(!i||!i.parentNode)return n.innerHTML;const r=i.parentNode,o=s("atai-generate-button",e.model.id,"modal");return r.replaceChild(o,i),n.innerHTML}})}window.atai=window.atai||{postsPerPage:1,lastPostId:0,intervals:{},redirectUrl:""},jQuery("[data-edit-history-trigger]").on("click",(async function(){const t=this,a=t.dataset.attachmentId,n=document.getElementById("edit-history-input-"+a).value.replace(/\n/g,"");t.disabled=!0;const i=await function(t,a=""){return t?new Promise(((e,n)=>{jQuery.ajax({type:"post",dataType:"json",data:{action:"atai_edit_history",security:wp_atai.security_edit_history,attachment_id:t,alt_text:a},url:wp_atai.ajax_url,success:function(t){e(t)},error:function(e){n(new Error("AJAX request failed"))}})})):Promise.reject(new Error(e("Attachment ID is missing","alttext-ai")))}(a,n);"success"!==i.status&&alert(e("Unable to update alt text for this image.","alttext-ai"));const r=document.getElementById("edit-history-success-"+a);r.classList.remove("hidden"),setTimeout((()=>{r.classList.add("hidden")}),2e3),t.disabled=!1})),jQuery("[data-bulk-generate-start]").on("click",(function(){const t=i("atai_action")||"normal",r=i("atai_batch_id")||0;"bulk-select-generate"!==t||r||alert(e("Invalid batch ID","alttext-ai")),window.atai.bulkGenerateKeywords=n(jQuery("[data-bulk-generate-keywords]").val()??""),window.atai.bulkGenerateNegativeKeywords=n(jQuery("[data-bulk-generate-negative-keywords]").val()??""),window.atai.progressWrapperEl=jQuery("[data-bulk-generate-progress-wrapper]"),window.atai.progressHeading=jQuery("[data-bulk-generate-progress-heading]"),window.atai.progressBarWrapper=jQuery("[data-bulk-generate-progress-bar-wrapper]"),window.atai.progressBarEl=jQuery("[data-bulk-generate-progress-bar]"),window.atai.progressPercent=jQuery("[data-bulk-generate-progress-percent]"),window.atai.progressLastPostId=jQuery("[data-bulk-generate-last-post-id]"),window.atai.progressCurrentEl=jQuery("[data-bulk-generate-progress-current]"),window.atai.progressCurrent=window.atai.progressBarEl.data("current"),window.atai.progressSuccessfulEl=jQuery("[data-bulk-generate-progress-successful]"),window.atai.progressSuccessful=window.atai.progressBarEl.data("successful"),window.atai.progressMax=window.atai.progressBarEl.data("max"),window.atai.progressButtonCancel=jQuery("[data-bulk-generate-cancel]"),window.atai.progressButtonFinished=jQuery("[data-bulk-generate-finished]"),"bulk-select-generate"===t?(window.atai.bulkGenerateMode="bulk-select",window.atai.bulkGenerateBatchId=r):(window.atai.bulkGenerateMode=jQuery("[data-bulk-generate-mode-all]").is(":checked")?"all":"missing",window.atai.bulkGenerateOnlyAttached=jQuery("[data-bulk-generate-only-attached]").is(":checked")?"1":"0",window.atai.bulkGenerateOnlyNew=jQuery("[data-bulk-generate-only-new]").is(":checked")?"1":"0",window.atai.bulkGenerateWCProducts=jQuery("[data-bulk-generate-wc-products]").is(":checked")?"1":"0",window.atai.bulkGenerateWCOnlyFeatured=jQuery("[data-bulk-generate-wc-only-featured]").is(":checked")?"1":"0"),jQuery("#bulk-generate-form").hide(),window.atai.progressWrapperEl.show(),a()})),jQuery("[data-bulk-generate-mode-all]").on("change",(function(){window.location.href=this.dataset.url})),jQuery("[data-bulk-generate-only-attached]").on("change",(function(){window.location.href=this.dataset.url})),jQuery("[data-bulk-generate-only-new]").on("change",(function(){window.location.href=this.dataset.url})),jQuery("[data-bulk-generate-wc-products]").on("change",(function(){window.location.href=this.dataset.url})),jQuery("[data-bulk-generate-wc-only-featured]").on("change",(function(){window.location.href=this.dataset.url})),jQuery("[data-post-bulk-generate]").on("click",(async function(t){if("#atai-bulk-generate"!==this.getAttribute("href"))return;if(t.preventDefault(),function(){try{if(window.wp&&wp.data&&wp.blocks)return wp.data.select("core/editor").isEditedPostDirty()}catch(e){return console.error("Error checking Gutenberg post dirty status: ",e),!0}return!0}()){if(!confirm(e("[AltText.ai] Make sure to save any changes before proceeding -- any unsaved changes will be lost. Are you sure you want to continue?","alttext-ai")))return}const a=document.getElementById("post_ID")?.value,i=this.querySelector("span"),r=this.nextElementSibling,s=i.innerText,o=document.querySelector("[data-post-bulk-generate-overwrite]")?.checked||!1,d=document.querySelector("[data-post-bulk-generate-process-external]")?.checked||!1,c=document.querySelector("[data-post-bulk-generate-keywords-checkbox]"),l=document.querySelector("[data-post-bulk-generate-keywords]"),u=c?.checked?n(l?.value):[];if(!a)return r.innerText=e("This is not a valid post.","alttext-ai"),void r.classList.add("atai-update-notice--error");this.classList.add("disabled"),i.innerText=e("Processing...","alttext-ai");const p=await function(t,a=!1,n=!1,i=[]){return t?new Promise(((r,s)=>{jQuery.ajax({type:"post",dataType:"json",data:{action:"atai_enrich_post_content",security:wp_atai.security_enrich_post_content,post_id:t,overwrite:a,process_external:n,keywords:i},url:wp_atai.ajax_url,success:function(e){r(e)},error:function(t){s(new Error(e("AJAX request failed","alttext-ai")))}})})):Promise.reject(new Error(e("Post ID is missing","alttext-ai")))}(a,o,d,u);if(p.success)window.location.reload();else{let t=e("Unable to generate alt text. Check error logs for details.","alttext-ai");r.innerText=t,r.classList.add("atai-update-notice--error")}this.classList.remove("disabled"),i.innerText=s})),document.addEventListener("DOMContentLoaded",(()=>{wp?.blocks&&jQuery.ajax({url:wp_atai.ajax_url,type:"GET",data:{action:"atai_check_enrich_post_content_transient",security:wp_atai.security_enrich_post_content_transient},success:function(e){e?.success&&wp.data.dispatch("core/notices").createNotice("success",e.data.message,{isDismissible:!0})}})})),jQuery('[name="handle_api_key"]').on("click",(function(){"Clear API Key"===this.value&&jQuery('[name="atai_api_key"]').val("")})),jQuery(".notice--atai.is-dismissible").on("click",".notice-dismiss",(function(){jQuery.ajax(wp_atai.ajax_url,{type:"POST",data:{action:"atai_expire_insufficient_credits_notice",security:wp_atai.security_insufficient_credits_notice}})})),document.addEventListener("DOMContentLoaded",(async()=>{const e=window.location.href.includes("post.php")&&jQuery("body").hasClass("post-type-attachment"),t=window.location.href.includes("post-new.php")||window.location.href.includes("post.php")&&!jQuery("body").hasClass("post-type-attachment"),a=window.location.href.includes("upload.php");let n=null,d="atai-generate-button";if(e){if(n=i("post"),!n)return!1;if(n=parseInt(n,10),!n)return;let e=document.getElementsByClassName("attachment-alt-text")[0];if(e){let t=s(d,n,"single");setTimeout((()=>{!function(e,t){if(e.hasChildNodes()){for(const a of e.childNodes)if("BUTTON"==a.nodeName)return void e.replaceChild(t,a);e.appendChild(t)}else e.appendChild(t)}(e,t)}),200)}}else{if(!a&&!t)return!1;if(n=i("item"),jQuery(document).on("click","ul.attachments li.attachment",(function(){let e=jQuery(this);e.attr("data-id")&&(n=parseInt(e.attr("data-id"),10),n&&r("alt-text-description",d,n))})),document.addEventListener("click",(function(e){o(e,"button-click",d)})),document.addEventListener("keydown",(function(e){"ArrowRight"!==e.key&&"ArrowLeft"!==e.key||o(e,"keyboard",d)})),!n)return!1;if(n){let e=0;window.atai.intervals.singleModal=setInterval((()=>{if(e++,e>30)return void clearInterval(window.atai.intervals.singleModal);if(n=parseInt(n,10),!n)return void clearInterval(window.atai.intervals.singleModal);r("alt-text-description",d,n)&&clearInterval(window.atai.intervals.singleModal)}),200)}}})),document.addEventListener("DOMContentLoaded",(()=>{jQuery('.tablenav .bulkactions select option[value="alttext_options"]').attr("disabled","disabled")})),document.addEventListener("DOMContentLoaded",(()=>{wp?.media?.view?.Attachment?.Details&&setTimeout(d,500)}))}();
