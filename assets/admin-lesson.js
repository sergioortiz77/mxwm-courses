console.log('[MXWM] admin-lesson.js v1.1 loaded');
window.mxwmUpdateCounters = function() {
    var labels = { pdf:"PRESENTACIÓN", podcast:"AUDIO", infographic:"IMAGEN", link:"LINK" };
    for(var type in window.mxwm_limits) {
        var el = document.getElementById("limit-count-"+type);
        if(el){
            var total = window.mxwm_current[type] + window.mxwm_pending[type];
            var color = (total >= window.mxwm_limits[type]) ? "red" : "green";
            el.innerHTML = "<b style=\'color:"+color+";\'>" + total + " / " + window.mxwm_limits[type] + "</b>";
        }
    }
};

window.mxwmRemovePendingMedia = function(e, id, type) {
    e.preventDefault();
    var el = document.getElementById("pending-res-"+id);
    if(el) el.remove();
    var currentIds = document.getElementById("mxwm_bulk_media_ids").value.split(",");
    currentIds = currentIds.filter(function(i) { return i != id && i !== ""; });
    document.getElementById("mxwm_bulk_media_ids").value = currentIds.join(",");
    
    if(type && window.mxwm_pending[type] > 0) window.mxwm_pending[type]--;
    window.mxwmUpdateCounters();
};

window.mxwmMarkForDeletion = function(e, id, type) {
    e.preventDefault();
    var li = document.getElementById("existing-res-" + id);
    if(li) li.style.display = "none";
    
    var container = document.getElementById("mxwm-resources-container");
    if(container) {
        container.insertAdjacentHTML("beforeend", "<input type=\'hidden\' name=\'mxwm_delete_resource[]\' value=\'" + id + "\'>");
    }
    
    if(type && window.mxwm_current[type] > 0) window.mxwm_current[type]--;
    window.mxwmUpdateCounters();
};

window.mxwmAddLinkRow = function() {
    if((window.mxwm_current["link"] + window.mxwm_pending["link"]) >= window.mxwm_limits["link"]) {
        alert("⚠️ BLOQUEO: Límite rebasado ("+window.mxwm_limits["link"]+") para: LINK\\n\\nHas alcanzado el límite máximo de links para esta lección.");
        return;
    }
    var tpl = `
    <div class="mxwm-link-item" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;">
        <input type="text" name="mxwm_new_res_link_title[]" placeholder="Título" style="flex:1;">
        <input type="url" name="mxwm_new_res_url[]" placeholder="https://..." style="flex:2;">
        <button type="button" class="button button-small" onclick="this.parentElement.remove(); window.mxwmUpdateLinkCounter(false);" style="color:#d63638;" title="Quitar">✖</button>
    </div>
    `;
    document.getElementById("mxwm-dynamic-links-container").insertAdjacentHTML("beforeend", tpl);
    window.mxwmUpdateLinkCounter(true);
};

window.mxwmUpdateLinkCounter = function(isAdding) {
    if(isAdding) window.mxwm_pending["link"]++;
    else if(window.mxwm_pending["link"] > 0) window.mxwm_pending["link"]--;
    window.mxwmUpdateCounters();
};

// Polling for the blue button injected by Gutenberg via REST API
var mxwmInitInterval = setInterval(function() {
    var btn = document.getElementById("mxwm_upload_media_btn");
    if(btn) {
        clearInterval(mxwmInitInterval); // Ya no buscar
        
        if(!btn.dataset.bound) { // Evitar enlazar doblemente
            btn.dataset.bound = "true";
            var mediaUploader;
            btn.addEventListener("click", function(e) {
                e.preventDefault();
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                if(typeof wp === "undefined" || !wp.media) {
                    alert("Cargando motor de media de WP... Un momento.");
                    return;
                }
                mediaUploader = wp.media.frames.file_frame = wp.media({
                    title: "Sube archivos o selecciónalos",
                    button: { text: "Preparar archivos para la Lección" },
                    multiple: true
                });
                mediaUploader.on("select", function() {
                    var selection = mediaUploader.state().get("selection");
                    var currentIds = document.getElementById("mxwm_bulk_media_ids").value;
                    var idsArray = currentIds ? currentIds.split(",") : [];
                    
                    selection.map(function(attachment) {
                        attachment = attachment.toJSON();
                        if(!idsArray.includes(attachment.id.toString())) {
                            var mime = attachment.mime || attachment.subtype || "";
                            var type = null;
                            if(mime.indexOf("pdf") !== -1) type = "pdf";
                            else if(mime.indexOf("audio") !== -1 || mime.indexOf("mpeg") !== -1) type = "podcast";
                            else if(mime.indexOf("image") !== -1) type = "infographic";
                            
                            if(type) {
                                if(window.mxwm_current[type] + window.mxwm_pending[type] >= window.mxwm_limits[type]) {
                                    alert("⚠️ BLOQUEO: Límite rebasado ("+window.mxwm_limits[type]+") para: " + type.toUpperCase() + "\\n\\nEl archivo " + attachment.filename + " excede tu métrica y ha sido descartado de la cola automáticamente.");
                                    return;
                                }
                                
                                window.mxwm_pending[type]++;
                                idsArray.push(attachment.id);
                                
                                var itemHtml = `
                                <div id="pending-res-${attachment.id}" style="padding:10px 15px; background:#f0f0f1; border-left:4px solid #00a0d2; display:flex; justify-content:space-between; align-items:center; border-radius:3px;">
                                    <span>📄 <strong style="color:#1d2327;">${attachment.filename}</strong> <em style="color:#28a745; font-size:12px; margin-left:5px;">En cola (Requiere Guardar)</em></span>
                                    <button type="button" class="mxwm-cancel-pending" data-id="${attachment.id}" data-type="${type}" style="color:#d63638; font-weight:bold; padding:2px 8px; background:transparent; border:none; cursor:pointer;">✖ Cancelar</button>
                                </div>
                                `;
                                
                                document.getElementById("mxwm_selected_files_preview").insertAdjacentHTML("beforeend", itemHtml);
                                
                                // Attach click listener DIRECTLY to the new cancel button
                                (function(attachId, attachType) {
                                    var newBtn = document.querySelector('#pending-res-' + attachId + ' .mxwm-cancel-pending');
                                    if (newBtn) {
                                        newBtn.addEventListener('click', function(evt) {
                                            evt.preventDefault();
                                            evt.stopPropagation();
                                            console.log('[MXWM] Cancelar clicked for ID:', attachId, 'Type:', attachType);
                                            window.mxwmRemovePendingMedia(evt, attachId, attachType);
                                        });
                                    }
                                })(attachment.id, type);
                            } else {
                                alert("Formato no soportado por el Syllabus: " + attachment.filename);
                            }
                        }
                    });
                    document.getElementById("mxwm_bulk_media_ids").value = idsArray.join(",");
                    window.mxwmUpdateCounters();
                });
                mediaUploader.open();
            });
        }
    }
}, 500);

// Event delegation for dynamically created "Cancelar" buttons
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.mxwm-cancel-pending');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    var id = btn.getAttribute('data-id');
    var type = btn.getAttribute('data-type');
    if (id) {
        window.mxwmRemovePendingMedia(e, parseInt(id, 10), type);
    }
});
