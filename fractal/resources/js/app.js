// resources/js/app.js
import './bootstrap';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import '../css/app.css';

window.Pusher = Pusher;

//console.log('app.js cargado (inicio)');

// 1) Crea Echo SOLO si aún no existe (por si algún Blade ya lo creó)
if (!window.Echo) {
  window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    wsHost: window.location.hostname,
    wsPort: 443,
    wssPort: 443,
    forceTLS: true,
    encrypted: true,
    enabledTransports: ['ws', 'wss'],
  });

  // Dispara EchoLoaded si nadie lo hizo aún
  window.dispatchEvent(new CustomEvent('EchoLoaded'));
}

// ==== Helpers (los dejamos por si luego quieres reutilizarlos) ====
function normalizeIds(list) {
  return []
    .concat(list || [])
    .map(v => {
      if (typeof v === 'object' && v !== null) {
        return v.id ?? v.client_id ?? v.cliente_id ?? v.value ?? null;
      }
      return v;
    })
    .filter(v => v !== null && v !== undefined && v !== '' && v !== 'undefined')
    .map(v => String(v).trim())
    .filter(v => /^\d+$/.test(v))
    .filter((v, i, a) => a.indexOf(v) === i);
}

function collectClientIds() {
  const fromWin =
    typeof window.chatClientId !== 'undefined' && window.chatClientId !== null
      ? [window.chatClientId]
      : [];
  const fromGlobal = Array.isArray(window.__GLOBAL_CHAT_IDS__)
    ? window.__GLOBAL_CHAT_IDS__
    : [];
  const fromDom = Array.from(document.querySelectorAll('[data-client-id]')).map(
    el => el.getAttribute('data-client-id'),
  );

  const ids = normalizeIds([...fromWin, ...fromGlobal, ...fromDom]);
  // console.log('🧩 chat IDs detectados:', ids);
  return ids;
}

// Antes usábamos esto para suscribir TODOS los clientes a chat.cliente.X,
// ahora el ChatWidget se encarga de suscribirse SOLO al cliente activo.
// Lo dejamos definido por si algún día lo quieres usar para otra cosa.
function subscribePrivateChatClients(ids) {
  ids.forEach(id => {
    if (!id || window['__chatSubscribed_' + id]) return;
    window['__chatSubscribed_' + id] = true;

    const name = `chat.cliente.${id}`; // coincide con channels.php

    window.Echo.private(name).listen('.mensaje-nuevo', e => {
      // Si el mensaje es mío, ignoro
      if (
        e?.message?.user?.id &&
        window.currentUserId &&
        String(e.message.user.id) === String(window.currentUserId)
      ) {
        return;
      }

      // Refrescar UI (si de verdad lo necesitas aquí)
      if (window.Livewire?.dispatch) {
        window.Livewire.dispatch('refreshMessages');
      }

      const icon = document.querySelector(`[data-client-id="${id}"]`);
      if (icon) icon.classList.add('has-unread');
    });
  });
}

function startSubscriptions() {
  //console.log('[Echo] startSubscriptions() llamado');

if (window.__estadoSubsInit) {
    return;
  }
  window.__estadoSubsInit = true;

  //console.log('[Echo] startSubscriptions() llamado');

  // ... resto de tu lógica de suscripción


  // =========================
  // CANAL PUBLICO: gestion-clientes
  // =========================
    const canal = 'gestion-clientes';
  //console.log('[Echo] Suscribiéndome a canal público:', canal);

const publicChannel = window.Echo.channel(canal);

publicChannel
// .listen('.EstadoCreditoUpdated', (e) => {
//   //console.log('[WS] EstadoCreditoUpdated recibido en gestion-clientes:', e);
//
//   if (!e || !e.cliente_id) {
//    //console.warn('❗ [WS] Evento sin cliente_id, ignorando');
//     return;
//   }
//
//   window.__estadoCreditoRefreshDebounced ??= (() => {
//     let t = null;
//     let last = 0;
//     const WAIT = 700;
//
//     return () => {
//       const now = Date.now();
//       clearTimeout(t);
//
//       const dispatchNow = () => {
//         last = Date.now();
//         if (window.Livewire?.dispatch) {
//           window.Livewire.dispatch('estado-credito-actualizado', {
//             cliente_id: e.cliente_id,
//           });
//         }
//       };
//
//       if (now - last > WAIT) {
//         dispatchNow();
//       } else {
//         t = setTimeout(dispatchNow, WAIT);
//       }
//     };
//   })();
//
//   window.__estadoCreditoRefreshDebounced();
// })
  
   .listen('.GestorUpdated', (e) => {
    //console.log('[WS] GestorUpdated recibido en gestion-clientes:', e);

    if (!e || !e.cliente_id) {
      //console.warn('❗ [WS] GestorUpdated sin cliente_id, ignorando');
      return;
    }

    // ¿Está el registro visible en esta tabla?
    const row = document.querySelector(`[data-cliente-id="${e.cliente_id}"]`);
    //console.log('[WS] Buscando registro visible para cliente', e.cliente_id, '=>', !!row);

    if (!row) {
      //console.log('🙈 [WS] Cliente no visible en esta tabla, no disparo Livewire');
      return;
    }

    if (window.Livewire?.dispatch) {
      //console.log('♻️ [WS] Livewire.dispatch("gestor-actualizado")', e);

      window.Livewire.dispatch('gestor-actualizado', {
        cliente_id: e.cliente_id,
        gestor_id: e.gestor_id ?? null,
        gestor_nombre: e.gestor_nombre ?? null,
      });
    } else {
      //console.warn('⚠️ [WS] Livewire.dispatch no existe');
    }
  })
  
    // NUEVA VERSIÓN
  .listen('.ClienteCreatedLight', (e) => {
    //console.log('[WS] ClienteCreatedLight recibido en gestion-clientes:', e);

    // ¿Está la tabla de ClienteResource visible?
    const table = document.querySelector(
      '.fi-resource-cliente .fi-ta-table, .fi-resource-clientes .fi-ta-table',
    );

    if (!table) {
      //console.log('[WS] ClienteResource no está visible en esta vista, no refresco');
      return;
    }

    // Suprimir loader un momento
    window.AppLoader?.suppress(800);

    // Sonido
    if (typeof window.playDing === 'function') {
      try {
        window.playDing();
      } catch (err) {
        //console.error('🔴 Error ejecutando window.playDing()', err);
      }
    } else {
      //console.warn('🔇 window.playDing no está definida');
    }

    // 👉 Aquí está la clave: evento dirigido para ListClientes
    if (window.Livewire?.dispatch && e?.cliente_id) {
      //console.log('♻️ [WS] Livewire.dispatch("cliente-creado")', e.cliente_id);
      window.Livewire.dispatch('cliente-creado', {
        clienteId: e.cliente_id,
      });
    } else {
      //console.warn('⚠️ [WS] Livewire.dispatch no existe o falta cliente_id');
    }
  })

// .listen('.ClienteUpdated', (e) => {
//   //console.log('🔥 [WS] ClienteUpdated recibido en gestion-clientes:', e);
//   if (window.Livewire?.dispatch) {
//     window.Livewire.dispatch('refresh');
//   }
// })
// .error((err) => {
//   //console.error('❌ [WS] Error en canal gestion-clientes:', err);
// });



  // =========================
  // CANAL person (si lo usas)
  // =========================
  window.Echo.channel('person')
    .listen('.PersonUpdated', (e) => {
      //console.log('🌟 [WS] PersonUpdated recibido:', e);
      if (window.Livewire?.dispatch) Livewire.dispatch('refresh');
    })
    .error((err) => console.error('❌ [WS] Error al suscribirse a person:', err));

  // =========================
  // CHAT PRIVADO EXISTENTE
  // =========================
  const ids = collectClientIds();
  if (!ids.length) {
    //console.log('⚠️ [Echo] chat: no hay clientIds aún (esperando DOM/Livewire).');
  } else {
   // console.log('🧩 [Echo] chat IDs detectados para suscripción privada:', ids);
    subscribePrivateChatClients(ids);
  }
}

// 3) Arrancar en TODOS los momentos posibles
if (window.Echo) startSubscriptions();
window.addEventListener('EchoLoaded', startSubscriptions);
document.addEventListener('DOMContentLoaded', startSubscriptions);
window.addEventListener('livewire:navigated', startSubscriptions);

// === Drag-to-Scroll para tablas Filament ===
(() => {
  if (window.__dragScrollInit) return;
  window.__dragScrollInit = true;


 //  const isInteractive = el => {
 //   if (!el) return false;
 //  const t = (el.tagName || '').toLowerCase();
 //   if (['input', 'select', 'textarea', 'button', 'a', 'label'].includes(t)) return true;
 //   if (el.closest('.ts-dropdown, .ts-control, .fi-input, .fi-select, .fi-forms')) return true;
 //   return false;
 // };

  const isInteractive = (el) => {
  if (!el) return false;

  return !!el.closest(
    'button, a, input, select, textarea, label, [role="button"], ' +
    '.ts-dropdown, .ts-control, .fi-input, .fi-select, .fi-forms, ' +
    '.fi-btn, .fi-ac-action, .fi-link'
  );
};


  const isScrollable = el =>
    el && (el.scrollWidth > el.clientWidth || el.scrollHeight > el.clientHeight);

  const findScroller = el => {
    let cur = el;
    while (cur && cur !== document.body) {
      const cs = getComputedStyle(cur);
      const any = /(auto|scroll)/.test(`${cs.overflow}${cs.overflowX}${cs.overflowY}`);
      if (any && isScrollable(cur)) return cur;
      cur = cur.parentElement;
    }
    return null;
  };

  const bindDrag = scroller => {
    if (!scroller || scroller.__dragBound) return;
    scroller.__dragBound = true;

    let down = false,
      sx = 0,
      sy = 0,
      sl = 0,
      st = 0;

    scroller.addEventListener(
      'pointerdown',
      e => {
        if (e.button !== 0) return; // solo botón izquierdo
        if (isInteractive(e.target)) return;
        down = true;
        sx = e.clientX;
        sy = e.clientY;
        sl = scroller.scrollLeft;
        st = scroller.scrollTop;
        scroller.classList.add('is-dragging');
        try {
          scroller.setPointerCapture(e.pointerId);
        } catch {}
      },
      { passive: true },
    );

    window.addEventListener(
      'pointermove',
      e => {
        if (!down) return;
        // preventDefault para que el drag funcione
        e.preventDefault();
        scroller.scrollLeft = sl - (e.clientX - sx);
        scroller.scrollTop = st - (e.clientY - sy);
      },
      { passive: false },
    );

    const up = e => {
      if (!down) return;
      down = false;
      scroller.classList.remove('is-dragging');
      if (e && e.pointerId) {
        try {
          scroller.releasePointerCapture(e.pointerId);
        } catch {}
      }
    };

    window.addEventListener('pointerup', up, { passive: true });
    window.addEventListener('pointercancel', up, { passive: true });
    window.addEventListener('pointerleave', up, { passive: true });
  };

  const init = () => {
    document
      .querySelectorAll(
        '.fi-resource-list-records-page .fi-ta, ' +
          '.fi-resource-list-records-page .fi-ta-content, ' +
          '.fi-resource-list-records-page .fi-ta-overflow-x-auto, ' +
          '.fi-resource-list-records-page .fi-overflow-x-auto, ' +
          '.fi-resource-list-records-page .fi-ta-table',
      )
      .forEach(node => {
        const scroller = findScroller(node) || node;
        if (isScrollable(scroller)) bindDrag(scroller);
      });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.addEventListener('livewire:navigated', init);
  if (window.Livewire?.hook) {
    window.Livewire.hook('message.processed', init);
  }
})();
