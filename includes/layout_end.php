    </div><!-- /.page-content -->
</div><!-- /.main -->
<script src="<?php echo APP_URL; ?>/assets/js/app.js"></script>
<script>
// Notification bell
(function(){
    var bell = document.getElementById('notif-bell');
    var dropdown = document.getElementById('notif-dropdown');
    var badge = document.getElementById('notif-badge');
    var list = document.getElementById('notif-list');
    var loaded = false;
    if (!bell) return;

    bell.addEventListener('click', function(e){
        e.stopPropagation();
        var showing = dropdown.style.display === 'block';
        dropdown.style.display = showing ? 'none' : 'block';
        if (!showing && !loaded) { loadNotifs(); loaded = true; }
    });
    document.addEventListener('click', function(){ dropdown.style.display = 'none'; });
    dropdown.addEventListener('click', function(e){ e.stopPropagation(); });

    function loadNotifs(){
        fetch('<?php echo APP_URL; ?>/api/notifications.php')
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d.notifications || d.notifications.length === 0){
                    list.innerHTML = '<div style="padding:16px;text-align:center;color:#8a9ab5;font-size:13px">No new notifications</div>';
                    return;
                }
                var html = '';
                d.notifications.forEach(function(n){
                    var icon = n.type==='success'?'✅':n.type==='warning'?'⚠️':n.type==='error'?'❌':'ℹ️';
                    var link = n.link ? 'href="'+n.link+'"' : 'href="#"';
                    html += '<a '+link+' style="display:block;padding:10px 16px;border-bottom:1px solid #1e3a5f;text-decoration:none;color:inherit" onclick="markNotifRead('+n.id+')">'
                          + '<div style="font-size:13px;font-weight:600">'+icon+' '+escHtml(n.title)+'</div>'
                          + (n.message ? '<div style="font-size:12px;color:#8a9ab5;margin-top:2px">'+escHtml(n.message)+'</div>' : '')
                          + '<div style="font-size:11px;color:#506070;margin-top:3px">'+escHtml(n.created_at||'')+'</div>'
                          + '</a>';
                });
                list.innerHTML = html;
                if (badge) { badge.textContent = d.unread; badge.style.display = d.unread > 0 ? 'block' : 'none'; }
            })
            .catch(function(){ list.innerHTML = '<div style="padding:16px;text-align:center;color:#8a9ab5;font-size:13px">Could not load notifications</div>'; });
    }

    window.markNotifRead = function(id){
        fetch('<?php echo APP_URL; ?>/api/notifications.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'mark_read',ids:[id]})})
            .then(function(){ loaded = false; loadNotifs(); });
    };
    window.markAllNotifRead = function(){
        fetch('<?php echo APP_URL; ?>/api/notifications.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'mark_read',ids:'all'})})
            .then(function(){ if(badge){badge.style.display='none';} list.innerHTML='<div style="padding:16px;text-align:center;color:#8a9ab5;font-size:13px">No new notifications</div>'; });
    };

    function escHtml(s){ var d=document.createElement('div');d.textContent=s;return d.innerHTML; }

    // Poll every 30 seconds
    setInterval(function(){
        fetch('<?php echo APP_URL; ?>/api/notifications.php')
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (badge) {
                    badge.textContent = d.unread || 0;
                    badge.style.display = (d.unread > 0) ? 'block' : 'none';
                }
                loaded = false;
            }).catch(function(){});
    }, 30000);
})();
</script>
</body>
</html>
