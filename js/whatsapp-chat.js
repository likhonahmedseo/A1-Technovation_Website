/**
 * A1 Technovation - Official WhatsApp Live Chat System
 * Phone: +8801799976295
 * Description: Interactive floating WhatsApp live chat widget for A1 Technovation Digital Marketing Agency.
 */

(function () {
  'use strict';

  var WA_PHONE = '8801799976295';
  var WA_DISPLAY_PHONE = '+880 1799-976295';

  // Ensure CSS stylesheet is present
  function ensureStyles() {
    if (!document.getElementById('wa-chat-stylesheet')) {
      var link = document.createElement('link');
      link.id = 'wa-chat-stylesheet';
      link.rel = 'stylesheet';
      link.href = '/css/whatsapp-chat.css';
      document.head.appendChild(link);
    }
  }

  // Construct and inject Live Chat DOM elements
  function initWhatsAppChat() {
    ensureStyles();

    // Avoid duplicate initialization
    if (document.getElementById('waChatRoot')) return;

    var root = document.createElement('div');
    root.id = 'waChatRoot';
    root.className = 'wa-chat-root not-prose';
    root.innerHTML = `
      <!-- Floating Trigger Button & Preview Tooltip -->
      <div class="wa-trigger-wrap" id="waTriggerWrap">
        <div class="wa-tooltip-pill" id="waTooltipPill" role="status" aria-live="polite">
          <i class="fab fa-whatsapp text-emerald-500 text-sm"></i>
          <span>Chat with our digital marketing team!</span>
          <button type="button" class="wa-tooltip-close" id="waTooltipClose" aria-label="Dismiss chat tooltip">&times;</button>
        </div>
        <button type="button" class="wa-trigger-btn" id="waTriggerBtn" aria-label="Open WhatsApp live chat" aria-expanded="false" aria-controls="waChatWindow">
          <i class="fab fa-whatsapp"></i>
          <span class="wa-status-dot" title="Support Online"></span>
          <span class="wa-notify-badge" id="waNotifyBadge">1</span>
        </button>
      </div>

      <!-- Live Chat Dialog Box -->
      <div class="wa-chat-window" id="waChatWindow" role="dialog" aria-modal="true" aria-labelledby="waChatHeading" aria-hidden="true">
        <!-- Header -->
        <div class="wa-chat-header">
          <div class="wa-header-brand">
            <div class="wa-avatar-wrap">
              <img src="/assets/images/CEO%20of%20A1%20Technovation.webp" alt="Likhon Ahmed - Founder & Lead Strategist" class="wa-avatar-img" />
              <span class="wa-avatar-dot"></span>
            </div>
            <div class="wa-header-meta">
              <h4 id="waChatHeading">A1 Technovation Agency <i class="fas fa-certificate text-cyan-400 text-xs" title="Verified Digital Marketing Agency"></i></h4>
              <p><span class="wa-pulse-dot"></span> SEO, AEO & Growth Specialists • Online</p>
            </div>
          </div>
          <div class="wa-header-actions">
            <i class="fab fa-whatsapp wa-header-wa-icon" title="WhatsApp Business Verified"></i>
            <button type="button" class="wa-close-btn" id="waCloseBtn" aria-label="Close chat window">&times;</button>
          </div>
        </div>

        <!-- Conversation Canvas -->
        <div class="wa-chat-body" id="waChatBody">
          <div class="wa-time-stamp">Today</div>
          
          <!-- Incoming Support Message Bubble -->
          <div class="wa-bubble-agent">
            <p><strong>👋 Hello! Welcome to A1 Technovation.</strong></p>
            <p>We are a results-driven <strong>Digital Marketing Agency</strong>. Looking to rank #1 on Google, capture AI search answers (AEO/GEO), or optimize your website speed and infrastructure for more organic conversions?</p>
            <p>Let's chat directly on WhatsApp!</p>
            <span class="wa-bubble-time">Just now <i class="fas fa-check-double text-blue-500 ms-1"></i></span>
          </div>

          <!-- Quick Consultation Chips Contextual to Digital Marketing & Hosting -->
          <div class="wa-chips-wrap">
            <span class="wa-chips-title">Frequent Inquiries:</span>
            <button type="button" class="wa-chip-btn" data-msg="Hi A1 Technovation! I'd like to request a free SEO & website performance audit for our business.">
              <span>🚀 Free SEO & Performance Audit</span>
              <i class="fas fa-chevron-right"></i>
            </button>
            <button type="button" class="wa-chip-btn" data-msg="Hello A1 Technovation! I need help with Local SEO & digital marketing strategy for my business.">
              <span>📍 Local SEO & Growth Strategy</span>
              <i class="fas fa-chevron-right"></i>
            </button>
            <button type="button" class="wa-chip-btn" data-msg="Hi! How can we optimize our hosting latency, server response time (TTFB), and Core Web Vitals to rank higher?">
              <span>⚡ Technical SEO & Server Speed</span>
              <i class="fas fa-chevron-right"></i>
            </button>
            <button type="button" class="wa-chip-btn" data-msg="Hello team! I would like to consult with an A1 Technovation digital marketing strategist about full-service growth.">
              <span>📈 Digital Marketing Consultation</span>
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>

        <!-- Footer / Input Form -->
        <div class="wa-chat-footer">
          <form id="waChatForm" class="wa-input-row" onsubmit="return false;">
            <input type="text" id="waChatInput" class="wa-input-field" placeholder="Ask about SEO, speed, or growth..." autocomplete="off" aria-label="Type your WhatsApp message" />
            <button type="submit" class="wa-send-btn" id="waSendBtn" aria-label="Send message via WhatsApp">
              <i class="fas fa-paper-plane"></i>
            </button>
          </form>
          <div class="wa-footer-note">
            <i class="fas fa-lock text-[9px] text-slate-400"></i>
            <span>Direct Agency WhatsApp • ${WA_DISPLAY_PHONE}</span>
          </div>
        </div>
      </div>
    `;

    document.body.appendChild(root);
    bindEvents();
  }

  // Bind interactive handlers
  function bindEvents() {
    var triggerBtn = document.getElementById('waTriggerBtn');
    var chatWindow = document.getElementById('waChatWindow');
    var closeBtn = document.getElementById('waCloseBtn');
    var tooltipPill = document.getElementById('waTooltipPill');
    var tooltipClose = document.getElementById('waTooltipClose');
    var notifyBadge = document.getElementById('waNotifyBadge');
    var chatForm = document.getElementById('waChatForm');
    var chatInput = document.getElementById('waChatInput');
    var chatBody = document.getElementById('waChatBody');
    var chips = document.querySelectorAll('.wa-chip-btn');

    var isOpen = false;

    function openChat() {
      isOpen = true;
      chatWindow.classList.add('is-open');
      chatWindow.setAttribute('aria-hidden', 'false');
      triggerBtn.setAttribute('aria-expanded', 'true');
      if (notifyBadge) notifyBadge.style.display = 'none';
      if (tooltipPill) tooltipPill.style.display = 'none';
      setTimeout(function () {
        if (chatInput) chatInput.focus();
      }, 150);
    }

    function closeChat() {
      isOpen = false;
      chatWindow.classList.remove('is-open');
      chatWindow.setAttribute('aria-hidden', 'true');
      triggerBtn.setAttribute('aria-expanded', 'false');
    }

    function toggleChat() {
      if (isOpen) {
        closeChat();
      } else {
        openChat();
      }
    }

    function sendToWhatsApp(messageText) {
      var text = (messageText || '').trim();
      if (!text) {
        text = 'Hello A1 Technovation team! I am visiting your website and would like to consult on digital marketing, SEO, and website performance solutions.';
      }

      // Add outgoing bubble to chat body
      var userBubble = document.createElement('div');
      userBubble.className = 'wa-bubble-user';
      userBubble.innerHTML = `
        <p>${escapeHtml(text)}</p>
        <span class="wa-bubble-time" style="color: #475569;">Connecting to WhatsApp... <i class="fas fa-check text-slate-500 ms-1"></i></span>
      `;
      chatBody.appendChild(userBubble);
      chatBody.scrollTop = chatBody.scrollHeight;

      // Construct official WhatsApp URL
      var waUrl = 'https://wa.me/' + WA_PHONE + '?text=' + encodeURIComponent(text);

      // On mobile: use location.href so the OS can intercept and open the WhatsApp app.
      // On desktop: open WhatsApp Web in a new tab.
      setTimeout(function () {
        var isMobile = /Android|iPhone|iPad|iPod|IEMobile|Opera Mini/i.test(navigator.userAgent);
        if (isMobile) {
          window.location.href = waUrl;
        } else {
          window.open(waUrl, '_blank', 'noopener,noreferrer');
        }
      }, 300);

      if (chatInput) {
        chatInput.value = '';
      }
    }

    function escapeHtml(str) {
      return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    // Trigger toggle
    if (triggerBtn) {
      triggerBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        toggleChat();
      });
    }

    // Tooltip pill opens chat
    if (tooltipPill) {
      tooltipPill.addEventListener('click', function (e) {
        e.stopPropagation();
        openChat();
      });
    }

    // Dismiss tooltip
    if (tooltipClose) {
      tooltipClose.addEventListener('click', function (e) {
        e.stopPropagation();
        tooltipPill.style.display = 'none';
      });
    }

    // Close button
    if (closeBtn) {
      closeBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        closeChat();
      });
    }

    // Click outside closes window
    document.addEventListener('click', function (e) {
      if (isOpen && chatWindow && !chatWindow.contains(e.target) && !triggerBtn.contains(e.target)) {
        closeChat();
      }
    });

    // Escape key closes window
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen) {
        closeChat();
      }
    });

    // Suggestion chips
    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        var msg = chip.getAttribute('data-msg') || chip.innerText;
        sendToWhatsApp(msg);
      });
    });

    // Form submit
    if (chatForm) {
      chatForm.addEventListener('submit', function (e) {
        e.preventDefault();
        sendToWhatsApp(chatInput.value);
      });
    }
  }

  // Initialize once DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWhatsAppChat);
  } else {
    initWhatsAppChat();
  }
})();
