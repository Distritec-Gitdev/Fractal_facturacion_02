<script>
/**
 * IIFE (Immediately Invoked Function Expression)
 * - Aísla variables/funciones para no contaminar el scope global.
 * - Se ejecuta apenas el navegador carga/parsea este <script>.
 */
(() => {

  // ====== Drag-to-scroll para tablas Filament ======

  /**
   * SCROLLABLE_SELECTORS:
   * Lista (array) de selectores CSS que apuntan a contenedores
   * que Filament usa para envolver tablas con overflow y scroll.
   * Al final hacemos .join(',') para convertirlo en un string "selector1,selector2,..."
   * apto para usar en querySelectorAll y .closest.
   */
  const SCROLLABLE_SELECTORS = [
    '.fi-ta-content',              // contenedor principal de la tabla en Filament
    '.fi-ta-overflow-x-auto',      // envoltorio con overflow-x automático
    '.fi-overflow-x-auto',         // variación genérica de overflow-x
    '.fi-ta-table .overflow-x-auto', // envoltorio interno con overflow-x
    '.fi-ta-table .overflow-y-auto', // envoltorio interno con overflow-y
    '.fi-ta-table .overflow-auto',   // envoltorio con overflow en ambas direcciones
    '[data-dragscroll]'              // cualquier elemento marcado manualmente para dragscroll
  ].join(',');

  /**
   * INTERACTIVE_SELECTOR:
   * Lista de elementos "interactivos". Si el usuario hace mousedown
   * sobre uno de estos, NO iniciamos el drag-to-scroll (para no
   * interferir con inputs, selects, links, etc.).
   */
  const INTERACTIVE_SELECTOR = [
    'input','textarea','select','button','a',
    '[contenteditable]','.fi-input','.fi-select','.fi-btn'
  ].join(',');

  /**
   * Insertamos estilos básicos para mostrar el cursor apropiado:
   * - cursor: grab cuando se puede arrastrar para hacer scroll
   * - cursor: grabbing cuando se está arrastrando
   *
   * Creamos un <style>, le asignamos reglas CSS y lo añadimos al <head>.
   */
  const style = document.createElement('style');
  style.textContent = `
    [data-dragscroll]{cursor:grab;}
    [data-dragscroll].dragging{cursor:grabbing!important;}
  `;
  document.head.appendChild(style);

  /**
   * Estado interno del drag:
   * - isDown: true desde que presionas hasta que sueltas (pointerdown → pointerup)
   * - moved: registra si realmente se movió (para anular el click fantasma)
   * - targetEl: el contenedor scrolleable actual que estamos arrastrando
   * - startX/startY: la posición del puntero al iniciar el drag
   * - startL/startT: scrollLeft/scrollTop del contenedor al iniciar
   */
  let isDown=false, moved=false, targetEl=null;
  let startX=0, startY=0, startL=0, startT=0;

  /**
   * isPrimary:
   * Verifica que el botón presionado sea el principal (izquierdo).
   * e.buttons puede no existir en algunos navegadores; por eso la doble condición.
   */
  const isPrimary = (e)=> e.button===0 && (e.buttons===undefined || e.buttons&1);

  /**
   * getScrollableAncestor:
   * Busca el ancestro más cercano del target real del evento que
   * coincida con alguno de los contenedores "scrolleables".
   */
  const getScrollableAncestor = (el) => el.closest(SCROLLABLE_SELECTORS);

  /**
   * markScrollable:
   * Recorre todos los elementos que coinciden con SCROLLABLE_SELECTORS.
   * Si tienen contenido más grande que su caja (overflow real en X o Y),
   * les añade el data-atributo data-dragscroll (para cursor y heurística).
   */
  function markScrollable(){
    document.querySelectorAll(SCROLLABLE_SELECTORS).forEach(el => {
      const hasOverflowX = el.scrollWidth  > el.clientWidth;
      const hasOverflowY = el.scrollHeight > el.clientHeight;
      if (hasOverflowX || hasOverflowY) el.setAttribute('data-dragscroll','');
    });
  }

  /**
   * onDown (pointerdown):
   * - Ignora clicks que no sean botón principal.
   * - Ignora si el down ocurrió sobre un elemento interactivo (para no romper UX).
   * - Detecta el contenedor scrolleable más cercano.
   * - Verifica que realmente tenga overflow (algo para desplazar).
   * - Registra estado inicial del drag y del scroll.
   * - Cambia userSelect para evitar selección de texto durante el drag.
   * - Añade clase .dragging para cambiar cursor.
   * - Intenta capturar el puntero (pointer capture) para recibir todos los moves.
   */
  function onDown(e){
    if(!isPrimary(e)) return;
    if(e.target.closest(INTERACTIVE_SELECTOR)) return;

    const scrollable = getScrollableAncestor(e.target);
    if (!scrollable) return;

    const hasOverflowX = scrollable.scrollWidth  > scrollable.clientWidth;
    const hasOverflowY = scrollable.scrollHeight > scrollable.clientHeight;
    if (!hasOverflowX && !hasOverflowY) return;

    targetEl = scrollable;
    isDown = true; moved = false;

    startX = e.clientX; startY = e.clientY;
    startL = targetEl.scrollLeft; startT = targetEl.scrollTop;

    document.body.style.userSelect = 'none';
    targetEl.classList?.add('dragging');
    try{ e.target.setPointerCapture?.(e.pointerId); }catch(_){}
  }

  /**
   * onMove (pointermove):
   * - Solo actúa si estamos en modo "arrastrando" (isDown y con target).
   * - Calcula desplazamiento del puntero (dx, dy).
   * - Marca "moved" si superó un umbral (3px) para distinguir de un simple click.
   * - Aplica el desplazamiento ajustando scrollLeft/scrollTop del contenedor.
   * - preventDefault() evita selección de texto y otros efectos no deseados.
   */
  function onMove(e){
    if(!isDown || !targetEl) return;
    const dx = e.clientX - startX;
    const dy = e.clientY - startY;

    if(Math.abs(dx)>3 || Math.abs(dy)>3) moved = true;

    targetEl.scrollLeft = startL - dx;
    targetEl.scrollTop  = startT - dy;

    // Importante: evita selección de texto
    e.preventDefault();
  }

  /**
   * onEnd (pointerup / pointercancel):
   * - Sale del modo "arrastrando".
   * - Si efectivamente hubo movimiento, cancela el primer click posterior
   *   (para que no dispare acciones en celdas/botones donde solo queríamos arrastrar).
   * - Restaura userSelect y quita la clase .dragging.
   * - Libera la captura del puntero si estaba activa.
   * - Limpia la referencia a targetEl.
   */
  function onEnd(e){
    if(!isDown) return;
    isDown=false;

    // Si arrastró, evita que el click "caiga" en celdas/acciones
    if(moved){
      const stopClick=(ce)=>{ ce.stopPropagation(); ce.preventDefault(); };
      document.addEventListener('click', stopClick, {capture:true, once:true});
    }

    document.body.style.userSelect='';
    targetEl?.classList?.remove('dragging');
    try{ e?.target?.releasePointerCapture?.(e.pointerId); }catch(_){}
    targetEl=null;
  }

  /**
   * init:
   * - Función idempotente que marca los contenedores scrolleables.
   * - Se llama en carga inicial y cada navegación interna (Livewire/Filament).
   */
  function init(){
    markScrollable();
  }

  /**
   * Suscripción a eventos de puntero a nivel ventana:
   * - pointerdown: detecta inicio del drag
   * - pointermove: desplaza el scroll mientras esté activo el drag
   * - pointerup / pointercancel: finaliza el drag
   *
   * Opciones passive:
   * - En move usamos {passive:false} porque llamamos e.preventDefault()
   *   (si fuera passive:true, el navegador lo prohibiría).
   * - En down/up/cancel podemos ir passive:true (no llamamos preventDefault).
   */
  window.addEventListener('pointerdown', onDown, {passive:true});
  window.addEventListener('pointermove', onMove,  {passive:false});
  window.addEventListener('pointerup',   onEnd,   {passive:true});
  window.addEventListener('pointercancel', onEnd, {passive:true});

  /**
   * Re-inicializaciones:
   * - DOMContentLoaded: cuando el documento termina de parsearse.
   * - livewire:navigated: cuando Livewire cambia de vista sin recargar.
   * - filament::page-rendered: evento de Filament v3 al renderizar una página del panel.
   *   (Duplicidad intencional para cubrir distintos flujos de navegación)
   */
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('livewire:navigated', init);
  // Filament v3 emite este evento al cambiar de página dentro del panel:
  document.addEventListener('filament::page-rendered', init);

})(); // <- fin de la IIFE: se ejecuta inmediatamente
</script>
