// public/js/chat-notifications.js
(function () {
  const DEBUG = false;
  const log   = (...a) => DEBUG && console.log('[chat-notifs]', ...a);
  const error = (...a) => console.error('[chat-notifs]', ...a);

  // Sonido (lazy)
  let _notificationSound = null;
  function getOrCreateSound() {
    if (_notificationSound) return _notificationSound;
    const a = new Audio('/sounds/notify.mp3');
    a.preload = 'auto';
    a.playsInline = true;
    a.volume = 0.5;

    if (!window.__chatAudioUnlocked) {
      const unlock = () => {
        a.play().catch(() => {});
        window.__chatAudioUnlocked = true;
        document.removeEventListener('click', unlock);
        document.removeEventListener('touchstart', unlock);
      };
      document.addEventListener('click', unlock, { once: true });
      document.addEventListener('touchstart', unlock, { once: true });
    }

    _notificationSound = a;
    return a;
  }

  async function ensureNotificationPermission() {
    if (!('Notification' in window)) return false;
    if (Notification.permission === 'granted') return true;
    if (Notification.permission === 'denied') return false;
    try {
      const res = await Notification.requestPermission();
      return res === 'granted';
    } catch { return false; }
  }

  function truncate(str, n = 180) {
    if (!str) return '';
    const s = String(str).replace(/\s+/g, ' ').trim();
    return s.length > n ? s.slice(0, n - 1) + '…' : s;
  }

  async function showBrowserNotification({ title, body, tag }) {
    if (!('Notification' in window)) return;
    let ok = Notification.permission === 'granted';
    if (!ok) ok = await ensureNotificationPermission();
    if (!ok) return;

    try {
      const n = new Notification(title, {
        body,
        icon: '/icons/chat.png',
        tag: tag || `chat-msg-${Date.now()}`,
        renotify: true,
        requireInteraction: true,
        timestamp: Date.now(),
      });
      n.onclick = () => { try { n.close(); } catch {} };
    } catch (e) {
      error('Notif error:', e);
    }
  }

  // Evitar duplicados
  const _seen = new Set();

  // Tracking del chat abierto (si tu app lo setea)
  window.currentOpenClientId ??= null;
  window.currentUserId ??= null;

  function shouldNotify(clientId, senderId) {
    // Ignorar mis propios mensajes
    if (senderId && window.currentUserId && String(senderId) === String(window.currentUserId)) return false;

    const windowActive = document.visibilityState === 'visible' && document.hasFocus();
    const sameChatOpen = String(window.currentOpenClientId || '') === String(clientId || '');
    return !(windowActive && sameChatOpen);
  }

  // ✅ Solo 1 suscripción por cliente seleccionado (on-demand)
  function subscribeToClient(clientId) {
    if (!window.Echo) return;
    if (!clientId) return;

    const key = `_chatSubscribed_${clientId}`;
    if (window[key]) return;
    window[key] = true;

    const channelName = `chat.cliente.${clientId}`;
    log('subscribe', channelName);

    window.Echo.private(channelName)
      .listen('.mensaje-nuevo', async (e) => {
        try {
          if (!e || e.kind !== 'message') return;

          const msgId = e?.message?.id ?? `${clientId}-${Date.now()}`;
          if (_seen.has(msgId)) return;
          _seen.add(msgId);
          if (_seen.size > 1200) {
            const it = _seen.values();
            for (let i = 0; i < 200; i++) _seen.delete(it.next().value);
          }

          const senderId = e?.message?.user?.id ?? null;
          const text = e?.message?.message ?? e?.message?.content ?? 'Nuevo mensaje';
          const fromName = e?.message?.user?.name ?? 'Cliente';

          if (shouldNotify(clientId, senderId)) {
            try { getOrCreateSound().play().catch(() => {}); } catch {}
            await showBrowserNotification({
              title: `Cliente #${clientId} • ${fromName}`,
              body: truncate(text, 180),
              tag: `chat-msg-${clientId}-${msgId}`
            });
          }
        } catch (err) {
          error('notif handler error:', err);
        }
      })
      .error((err) => error('Echo subscribe error:', err));
  }

  function init() {
    // cuando tu UI elige un cliente
    window.addEventListener('clientSelected', (e) => {
      const id = Number(e.detail?.id ?? e.detail);
      if (!id) return;
      window.currentOpenClientId = id;
      subscribeToClient(id);
    });
  }

  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('livewire:init', init);
})();
