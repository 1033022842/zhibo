/* 订阅页「What users are saying」桌面端的左右翻页：遮罩 + 圆箭头。
   参考站这两个控件由 candy 的 scroll-fade 控制器生成，我们的应用包（旧版）里没有该控制器，
   这里补一份等价实现：按能否继续滚动来显隐遮罩/箭头，点箭头按一屏滚动。 */
(function () {
  'use strict'

  function setup(root) {
    var scroller = root.querySelector('[data-scroll-fade-target="scrollContainer"]')
    var leftFade = root.querySelector('[data-scroll-fade-target="leftFade"]')
    var rightFade = root.querySelector('[data-scroll-fade-target="rightFade"]')
    var leftBtn = root.querySelector('[data-scroll-fade-target="leftButton"]')
    var rightBtn = root.querySelector('[data-scroll-fade-target="rightButton"]')
    if (!scroller || !leftFade || !rightFade || !leftBtn || !rightBtn) return

    function update() {
      var max = scroller.scrollWidth - scroller.clientWidth
      var canLeft = scroller.scrollLeft > 4
      var canRight = max > 4 && scroller.scrollLeft < max - 4
      leftFade.style.opacity = canLeft ? '1' : '0'
      rightFade.style.opacity = canRight ? '1' : '0'
      leftBtn.style.opacity = canLeft ? '1' : '0'
      rightBtn.style.opacity = canRight ? '1' : '0'
      leftBtn.style.pointerEvents = canLeft ? 'auto' : 'none'
      rightBtn.style.pointerEvents = canRight ? 'auto' : 'none'
    }

    function page(dir, step) {
      var s = step || Math.max(Math.round(scroller.clientWidth * 0.8), 300)
      if (scroller.scrollBy) scroller.scrollBy({ left: dir * s, behavior: 'smooth' })
      else scroller.scrollLeft = scroller.scrollLeft + dir * s
    }

    leftBtn.addEventListener('click', function (e) { e.preventDefault(); page(-1) })
    rightBtn.addEventListener('click', function (e) { e.preventDefault(); page(1) })
    scroller.addEventListener('scroll', update, { passive: true })
    var wheelLock = 0
    scroller.addEventListener('wheel', function (e) {
      // 触控板横向滚动交给浏览器；纵向滚轮按“一张卡”翻页（容器是 snap-mandatory，
      // 小位移会被吸附回去，所以这里按整卡步进，且做 260ms 节流防止连发）
      if (e.deltaX) return
      var max = scroller.scrollWidth - scroller.clientWidth
      if (max <= 4) return
      if (e.deltaY > 0 && scroller.scrollLeft >= max - 4) return
      if (e.deltaY < 0 && scroller.scrollLeft <= 4) return
      e.preventDefault()
      var now = Date.now()
      if (now - wheelLock < 260) return
      wheelLock = now
      var card = scroller.firstElementChild
      var step = card ? card.getBoundingClientRect().width + 20 : 320
      page(e.deltaY > 0 ? 1 : -1, step)
    }, { passive: false })
    window.addEventListener('resize', update)
    update()
    setTimeout(update, 300)
  }

  function boot() {
    var blocks = document.querySelectorAll('[data-subscription-social-proof]')
    for (var i = 0; i < blocks.length; i++) setup(blocks[i])
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot)
  else boot()
  window.addEventListener('load', boot)
})()
