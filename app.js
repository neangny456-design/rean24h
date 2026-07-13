// English24h - Frontend Controller Script

// TG WebApp Config
let tg = null;
if (typeof window !== 'undefined' && window.Telegram && window.Telegram.WebApp) {
  tg = window.Telegram.WebApp;
  tg.ready();
  tg.expand();
  if (tg.setHeaderColor) {
    tg.setHeaderColor('#0b0f19');
  }
}

// App State Management
const state = {
  studentName: localStorage.getItem('english24h_student_name') || '',
  questions: [], // Loaded from database via API (Admin)
  scoreLogs: [], // Loaded from database via API (Student & Admin)
  
  // Quiz Configurations (Synced from database settings table)
  quizTimer: 30,
  quizQuestionsCount: 5,
  
  // Current session test state
  currentQuizTense: '',
  quizQuestions: [],
  currentQuestionIdx: 0,
  scoreCount: 0,
  timerInterval: null,
  timerSeconds: 30,
  timeStart: null,
  
  // Navigation stack
  viewHistory: []
};

// Initializing the application
document.addEventListener('DOMContentLoaded', () => {
  initApp();
});

async function initApp() {
  // Pre-fill student name from Telegram user object if available
  if (!state.studentName && tg && tg.initDataUnsafe && tg.initDataUnsafe.user) {
    const user = tg.initDataUnsafe.user;
    state.studentName = `${user.first_name || ''} ${user.last_name || ''}`.trim() || user.username || '';
  }

  // Set initial screen
  if (state.studentName) {
    document.getElementById('student-name-input').value = state.studentName;
    document.getElementById('display-student-name').textContent = state.studentName;
    updateAvatar(state.studentName);
    await loadStudentLogs(); // Fetch stats from DB
    showView('view-dashboard');
  } else {
    showView('view-welcome');
  }

  // Register Event Listeners
  registerEventListeners();
  populateTenseDropdowns();
  
  // Load global app settings configurations (timer, question count)
  await loadSettings();
  
  // Load custom certificate assets (logo, border, signature)
  await loadCertificateAssets();
  
  // Load active tenses/categories on student dashboard dynamically
  await loadDashboardTenses();
}

// Populate tenses filter in Admin and Add QCM form
function populateTenseDropdowns() {
  const tenses = [
    "Simple Present", "Present Continuous", "Present Perfect", "Present Perfect Continuous",
    "Simple Past", "Past Continuous", "Past Perfect", "Past Perfect Continuous",
    "Simple Future", "Future Continuous", "Future Perfect", "Future Perfect Continuous"
  ];
  
  const filterSelect = document.getElementById('admin-filter-tense');
  if (filterSelect) {
    filterSelect.innerHTML = '<option value="all">All Tenses</option>';
    tenses.forEach(tense => {
      const option = document.createElement('option');
      option.value = tense;
      option.textContent = tense;
      filterSelect.appendChild(option);
    });
  }
}

function registerEventListeners() {
  // Welcome View
  document.getElementById('btn-start-app').addEventListener('click', handleStartApp);
  document.getElementById('btn-goto-login').addEventListener('click', () => showView('view-admin-login'));

  // Dashboard View
  document.getElementById('btn-change-name').addEventListener('click', () => showView('view-welcome'));
  document.getElementById('btn-view-my-scores').addEventListener('click', showHistoryView);
  document.getElementById('btn-view-my-certs').addEventListener('click', () => { renderCertificatesGallery(); showView('view-certificates-list'); });
  document.getElementById('btn-dashboard-admin').addEventListener('click', () => showView('view-admin-login'));



  // History View
  document.getElementById('btn-history-back').addEventListener('click', () => showView('view-dashboard'));
  document.getElementById('btn-certs-list-back').addEventListener('click', () => showView('view-dashboard'));
  document.getElementById('btn-clear-history').addEventListener('click', clearScoreHistory);

  // Quiz View
  document.getElementById('btn-quiz-quit').addEventListener('click', quitQuizPrompt);
  document.getElementById('btn-quiz-next').addEventListener('click', nextQuestion);

  // Results View
  document.getElementById('btn-result-certificate').addEventListener('click', showCertificate);
  document.getElementById('btn-result-retry').addEventListener('click', () => startQuiz(state.currentQuizTense));
  document.getElementById('btn-result-dashboard').addEventListener('click', () => showView('view-dashboard'));

  // Certificate View
  document.getElementById('btn-print-cert').addEventListener('click', saveCertificatePDF);
  document.getElementById('btn-download-png').addEventListener('click', downloadCertificatePNG);
  document.getElementById('btn-cert-back').addEventListener('click', () => showView('view-results'));

  // Mobile Certificate Preview Modal View
  document.getElementById('btn-close-preview-modal').addEventListener('click', () => {
    document.getElementById('modal-image-preview').classList.add('hidden');
  });
  document.getElementById('btn-save-localstorage-preview').addEventListener('click', saveToLocalStorageAction);

  // Admin Login View
  document.getElementById('btn-admin-login-back').addEventListener('click', () => {
    if (state.studentName) {
      showView('view-dashboard');
    } else {
      showView('view-welcome');
    }
  });
  document.getElementById('admin-login-form').addEventListener('submit', handleAdminLogin);

  // Admin Dashboard View
  document.getElementById('btn-admin-logout').addEventListener('click', handleAdminLogout);
  document.getElementById('btn-admin-back-to-dashboard').addEventListener('click', () => {
    if (state.studentName) {
      showView('view-dashboard');
    } else {
      showView('view-welcome');
    }
  });

  // Admin Tabs
  document.getElementById('tab-questions').addEventListener('click', () => switchAdminTab('questions'));
  document.getElementById('tab-scores').addEventListener('click', () => switchAdminTab('scores'));
  document.getElementById('tab-customize').addEventListener('click', () => {
    switchAdminTab('customize');
    loadSettings();
  });
  document.getElementById('admin-customize-form').addEventListener('submit', handleCustomizeSubmit);
  document.getElementById('admin-settings-form').addEventListener('submit', handleSettingsSubmit);
  document.getElementById('admin-filter-tense').addEventListener('change', renderAdminQuestionsList);
  document.getElementById('btn-admin-clear-logs').addEventListener('click', clearAdminScoreLogs);

  // Question Editor Modal
  document.getElementById('btn-show-add-question-modal').addEventListener('click', () => showQuestionModal());
  document.getElementById('btn-close-editor-modal').addEventListener('click', hideQuestionModal);
  document.getElementById('btn-cancel-editor-modal').addEventListener('click', hideQuestionModal);
  document.getElementById('question-editor-form').addEventListener('submit', saveQuestion);

  // Telegram SDK BackButton Integration
  if (tg && tg.BackButton) {
    tg.BackButton.onClick(() => {
      handleBackNavigation();
    });
  }
}

// Router and Navigation functions
function showView(viewId) {
  // Hide all containers
  const views = document.querySelectorAll('.view-container');
  views.forEach(v => v.classList.add('hidden'));

  // Show target container
  const targetView = document.getElementById(viewId);
  if (targetView) {
    targetView.classList.remove('hidden');
    window.scrollTo(0, 0);
  }

  // Manage navigation stack (avoid duplicates)
  if (state.viewHistory.length === 0 || state.viewHistory[state.viewHistory.length - 1] !== viewId) {
    state.viewHistory.push(viewId);
  }

  // Handle Telegram back button
  updateTelegramBackButtonState(viewId);

  // Specific view actions
  if (viewId === 'view-dashboard') {
    updateStats();
    loadDashboardTenses();
  } else if (viewId === 'view-admin-dashboard') {
    loadAdminDashboardData();
  }
}

function handleBackNavigation() {
  if (state.viewHistory.length <= 1) {
    if (tg) tg.close();
    return;
  }

  const currentView = state.viewHistory.pop();

  if (currentView === 'view-quiz') {
    quitQuizPrompt();
    state.viewHistory.push(currentView);
    return;
  }

  const previousView = state.viewHistory[state.viewHistory.length - 1] || 'view-welcome';
  
  if (currentView === 'view-admin-dashboard' || currentView === 'view-admin-login') {
    if (state.studentName) {
      showView('view-dashboard');
    } else {
      showView('view-welcome');
    }
  } else if (currentView === 'view-certificate') {
    showView('view-results');
  } else if (currentView === 'view-results' || currentView === 'view-history') {
    showView('view-dashboard');
  } else {
    showView(previousView);
  }
}

function updateTelegramBackButtonState(viewId) {
  if (!tg || !tg.BackButton) return;

  if (viewId === 'view-welcome' || (viewId === 'view-dashboard' && state.viewHistory.length <= 1)) {
    tg.BackButton.hide();
  } else {
    tg.BackButton.show();
  }
}

// UI Helpers
function updateAvatar(name) {
  const avatarChar = document.getElementById('student-avatar-char');
  if (avatarChar && name) {
    avatarChar.textContent = name.trim().charAt(0).toUpperCase();
  }
}

function showToast(message, type = 'info') {
  const toast = document.getElementById('toast');
  toast.className = `toast show ${type}`;
  
  let icon = '<i class="fa-solid fa-circle-info"></i>';
  if (type === 'success') icon = '<i class="fa-solid fa-circle-check"></i>';
  if (type === 'error') icon = '<i class="fa-solid fa-triangle-exclamation"></i>';

  toast.innerHTML = `${icon} <span>${message}</span>`;

  setTimeout(() => {
    toast.classList.remove('show');
  }, 3000);
}

// Student database log sync
async function loadStudentLogs() {
  if (!state.studentName) return;
  try {
    const response = await fetch(`api.php?action=get_my_logs&name=${encodeURIComponent(state.studentName)}`);
    const data = await response.json();
    if (data.success) {
      state.scoreLogs = data.logs;
      updateStats();
    }
  } catch (err) {
    console.error("Error loading student logs:", err);
  }
}

// Student flow logic
async function handleStartApp() {
  const nameInput = document.getElementById('student-name-input').value.trim();
  
  if (!nameInput) {
    showToast("Please enter your name.", "error");
    return;
  }

  state.studentName = nameInput;
  localStorage.setItem('english24h_student_name', nameInput);
  
  document.getElementById('display-student-name').textContent = nameInput;
  updateAvatar(nameInput);
  showToast(`Welcome, ${nameInput}!`, "success");
  
  await loadStudentLogs();
  showView('view-dashboard');
}

function updateStats() {
  const scores = state.scoreLogs;
  const count = scores.length;
  document.getElementById('stat-tests-taken').textContent = count;

  let totalPercent = 0;
  scores.forEach(log => totalPercent += log.scorePercent);
  const avg = count > 0 ? Math.round(totalPercent / count) : 0;
  document.getElementById('stat-avg-score').textContent = `${avg}%`;

  const masterList = {};
  scores.forEach(log => {
    if (!masterList[log.tense] || log.scorePercent > masterList[log.tense]) {
      masterList[log.tense] = log.scorePercent;
    }
  });

  let masteredCount = 0;
  Object.values(masterList).forEach(pct => {
    if (pct >= 80) masteredCount++;
  });

  document.getElementById('stat-streak').textContent = masteredCount;
}

async function showHistoryView() {
  await loadStudentLogs();
  const rowsContainer = document.getElementById('history-rows');
  rowsContainer.innerHTML = '';

  if (state.scoreLogs.length === 0) {
    rowsContainer.innerHTML = '<tr><td colspan="3" class="text-center">No tests completed yet.</td></tr>';
  } else {
    state.scoreLogs.forEach(log => {
      const tr = document.createElement('tr');
      let badgeClass = 'score-low';
      if (log.scorePercent >= 80) badgeClass = 'score-high';
      else if (log.scorePercent >= 50) badgeClass = 'score-medium';

      let certBtnHtml = '';
      if (log.scorePercent >= 60) {
        certBtnHtml = `
          <button class="btn btn-sm" onclick="viewHistoryCertificate('${log.tense}', ${log.scorePercent}, '${log.date}')" style="padding: 2px 6px; font-size: 0.72rem; background: var(--gold-gradient); color: #0b0f19; font-weight: 600; border-radius: 4px;">
            <i class="fa-solid fa-award"></i> Cert
          </button>
        `;
      }

      tr.innerHTML = `
        <td>${log.date}</td>
        <td style="font-weight: 500;">${log.tense}</td>
        <td>
          <div style="display:flex; align-items:center; justify-content:space-between; gap:6px;">
            <span class="score-badge ${badgeClass}">${log.scorePercent}% (${log.scoreFraction})</span>
            ${certBtnHtml}
          </div>
        </td>
      `;
      rowsContainer.appendChild(tr);
    });
  }

  showView('view-history');
}

async function clearScoreHistory() {
  if (confirm("Are you sure you want to clear your practice history?")) {
    try {
      const response = await fetch(`api.php?action=clear_my_logs&name=${encodeURIComponent(state.studentName)}`);
      const data = await response.json();
      if (data.success) {
        state.scoreLogs = [];
        showToast("History cleared successfully.", "success");
        showHistoryView();
      } else {
        showToast(data.error || "Failed to clear history.", "error");
      }
    } catch (err) {
      console.error(err);
      showToast("Server connection error.", "error");
    }
  }
}

// QUIZ SYSTEM LOGIC (MySQL Powered)
async function startQuiz(tense) {
  state.currentQuizTense = tense;
  
  try {
    const response = await fetch(`api.php?action=get_questions&tense=${encodeURIComponent(tense)}`);
    const data = await response.json();
    
    if (!data.success) {
      showToast(data.error || "Failed to load questions.", "error");
      return;
    }
    
    if (data.questions.length === 0) {
      showToast("No questions available for this tense. Add some in the Admin panel!", "error");
      return;
    }

    state.quizQuestions = data.questions;
    
    state.currentQuestionIdx = 0;
    state.scoreCount = 0;
    state.timeStart = Date.now();
    
    document.getElementById('quiz-category-badge').textContent = tense;
    document.getElementById('quiz-live-score').textContent = `Score: 0`;

    showView('view-quiz');
    loadQuestion();
  } catch (err) {
    console.error("Error starting quiz:", err);
    showToast("Server connection error.", "error");
  }
}

function loadQuestion() {
  clearInterval(state.timerInterval);

  const qData = state.quizQuestions[state.currentQuestionIdx];
  const total = state.quizQuestions.length;
  
  document.getElementById('quiz-question-counter').textContent = `Question ${state.currentQuestionIdx + 1} of ${total}`;
  
  const progressPercent = (state.currentQuestionIdx / total) * 100;
  document.getElementById('quiz-progress-fill').style.width = `${progressPercent}%`;
  document.getElementById('question-text').textContent = qData.question;

  const choicesContainer = document.getElementById('choices-container');
  choicesContainer.innerHTML = '';

  const alphabet = ['A', 'B', 'C', 'D'];
  qData.options.forEach((opt, idx) => {
    const btn = document.createElement('button');
    btn.className = 'btn-choice';
    btn.setAttribute('data-letter', alphabet[idx]);
    btn.textContent = opt;
    btn.addEventListener('click', () => handleOptionSelection(idx, btn));
    choicesContainer.appendChild(btn);
  });

  document.getElementById('btn-quiz-next').classList.add('hidden');

  state.timerSeconds = state.quizTimer || 30;
  const timerDisplay = document.getElementById('timer-seconds');
  const timerDiv = document.getElementById('quiz-timer-display');
  timerDisplay.textContent = state.timerSeconds;
  timerDiv.classList.remove('warning');

  state.timerInterval = setInterval(() => {
    state.timerSeconds--;
    timerDisplay.textContent = state.timerSeconds;
    
    if (state.timerSeconds <= 5) {
      timerDiv.classList.add('warning');
    }
    
    if (state.timerSeconds <= 0) {
      clearInterval(state.timerInterval);
      handleTimeOut();
    }
  }, 1000);
}

function handleOptionSelection(selectedIdx, selectedBtn) {
  clearInterval(state.timerInterval);

  const qData = state.quizQuestions[state.currentQuestionIdx];
  const correctIdx = qData.correctIdx;
  const choices = document.querySelectorAll('.btn-choice');
  
  choices.forEach(btn => btn.disabled = true);

  if (selectedIdx === correctIdx) {
    selectedBtn.classList.add('correct');
    state.scoreCount++;
    document.getElementById('quiz-live-score').textContent = `Score: ${state.scoreCount}`;
    showToast("Correct Answer!", "success");
  } else {
    selectedBtn.classList.add('incorrect');
    choices[correctIdx].classList.add('correct');
    showToast("Incorrect Answer", "error");
  }

  document.getElementById('btn-quiz-next').classList.remove('hidden');
}

function handleTimeOut() {
  const qData = state.quizQuestions[state.currentQuestionIdx];
  const correctIdx = qData.correctIdx;
  const choices = document.querySelectorAll('.btn-choice');

  choices.forEach(btn => btn.disabled = true);
  choices[correctIdx].classList.add('correct');
  
  showToast("Time's Up!", "error");
  document.getElementById('btn-quiz-next').classList.remove('hidden');
}

function nextQuestion() {
  state.currentQuestionIdx++;
  
  if (state.currentQuestionIdx < state.quizQuestions.length) {
    loadQuestion();
  } else {
    finishQuiz();
  }
}

function quitQuizPrompt() {
  if (confirm("Are you sure you want to quit this test? Your progress will not be saved.")) {
    clearInterval(state.timerInterval);
    showView('view-dashboard');
  }
}

async function finishQuiz() {
  clearInterval(state.timerInterval);
  
  const totalQuestions = state.quizQuestions.length;
  const percentage = Math.round((state.scoreCount / totalQuestions) * 100);
  const fraction = `${state.scoreCount}/${totalQuestions}`;

  const timeDiffSecs = Math.round((Date.now() - state.timeStart) / 1000);
  const minutes = Math.floor(timeDiffSecs / 60);
  const seconds = timeDiffSecs % 60;
  const timeString = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;

  try {
    // Save to remote MySQL Database via API
    await fetch('api.php?action=submit_score', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        studentName: state.studentName,
        tense: state.currentQuizTense,
        scoreFraction: fraction,
        scorePercent: percentage,
        timeTaken: timeString
      })
    });
    
    await loadStudentLogs(); // Refresh dashboard counts
  } catch (err) {
    console.error("Error saving score to database:", err);
  }

  // Render Results Page
  document.getElementById('result-score-percent').textContent = `${percentage}%`;
  document.getElementById('result-score-fraction').textContent = fraction;
  document.getElementById('result-topic').textContent = state.currentQuizTense;
  document.getElementById('result-time-taken').textContent = timeString;

  const glow = document.getElementById('result-glow-circle');
  const scoreCircle = document.querySelector('.score-circle');
  if (percentage >= 80) {
    document.getElementById('result-title').textContent = "Excellent Job!";
    document.getElementById('result-feedback').textContent = "Incredible accuracy! You have fully mastered this tense.";
    document.getElementById('result-status').textContent = "Mastered";
    document.getElementById('result-status').style.color = "#10b981";
    glow.style.background = "#10b981";
    scoreCircle.style.borderColor = "#10b981";
  } else if (percentage >= 50) {
    document.getElementById('result-title').textContent = "Good Effort!";
    document.getElementById('result-feedback').textContent = "You are doing well, but review a few rules to reach mastery.";
    document.getElementById('result-status').textContent = "Passed";
    document.getElementById('result-status').style.color = "#f59e0b";
    glow.style.background = "#f59e0b";
    scoreCircle.style.borderColor = "#f59e0b";
  } else {
    document.getElementById('result-title').textContent = "Keep Practicing!";
    document.getElementById('result-feedback').textContent = "Try reviewing the grammatical structures and try again!";
    document.getElementById('result-status').textContent = "Try Again";
    document.getElementById('result-status').style.color = "#ef4444";
    glow.style.background = "#ef4444";
    scoreCircle.style.borderColor = "#ef4444";
  }

  // Show Certificate button only for Grades A, B, C (score >= 60%)
  const btnCert = document.getElementById('btn-result-certificate');
  if (percentage >= 60) {
    btnCert.classList.remove('hidden');
  } else {
    btnCert.classList.add('hidden');
  }

  showView('view-results');

  // Trigger Async Telegram Bot Notification
  sendTelegramNotification(state.studentName, state.currentQuizTense, fraction, percentage, timeString);

  // Auto-send certificate PDF to Telegram Bot if student got Grade A, B, or C (percentage >= 60%)
  if (percentage >= 60) {
    setTimeout(() => {
      autoSendCertificateTelegram(percentage);
    }, 800);
  }
}

// Automatically render and transmit the certificate PDF file to the student's telegram chat inbox
async function autoSendCertificateTelegram(percentage) {
  if (!tg || !tg.initDataUnsafe || !tg.initDataUnsafe.user) return;
  const chatId = tg.initDataUnsafe.user.id;
  if (!chatId) return;

  const view = document.getElementById('view-certificate');
  if (!view) return;

  const originalStyle = view.style.cssText;
  const originalHidden = view.classList.contains('hidden');

  // Populate certificate details
  document.getElementById('cert-student-name').textContent = state.studentName;
  document.getElementById('cert-tense-name').textContent = state.currentQuizTense;
  
  let grade = 'C';
  if (percentage >= 90) grade = 'A';
  else if (percentage >= 75) grade = 'B';
  document.getElementById('cert-grade').textContent = grade;
  
  const dateOptions = { day: 'numeric', month: 'long', year: 'numeric' };
  const todayString = new Date().toLocaleDateString('en-US', dateOptions);
  document.getElementById('cert-date-text').textContent = todayString;

  // Render gold frame custom border if exists
  await loadCertificateAssets();

  // Temporarily make container layout visible off-screen for html2canvas compilation
  view.style.cssText = 'position: absolute !important; top: -9999px !important; left: -9999px !important; display: block !important; visibility: visible !important; opacity: 0 !important;';
  view.classList.remove('hidden');

  const container = document.getElementById('certificate-container');
  const originalBoxShadow = container.style.boxShadow;
  container.style.boxShadow = 'none';

  try {
    const canvas = await html2canvas(container, {
      scale: 2.0, // High quality, fast rendering scale
      width: 840,
      height: 594,
      scrollX: 0,
      scrollY: 0,
      windowWidth: 840,
      windowHeight: 594,
      useCORS: true,
      allowTaint: true,
      logging: false,
      backgroundColor: '#ffffff'
    });

    // Revert styling states
    container.style.boxShadow = originalBoxShadow;
    view.style.cssText = originalStyle;
    if (originalHidden) {
      view.classList.add('hidden');
    } else {
      view.classList.remove('hidden');
    }

    const pngDataUrl = canvas.toDataURL('image/png');

    // Generate PDF using jsPDF
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({
      orientation: 'landscape',
      unit: 'px',
      format: [840, 594]
    });
    pdf.addImage(pngDataUrl, 'PNG', 0, 0, 840, 594);
    const pdfDataUrl = pdf.output('datauristring');

    // Transmit certificate PDF to user's Telegram Chat
    await sendCertToTelegram(pdfDataUrl, 'pdf');

  } catch (error) {
    console.error("Auto-sender certificate rendering failed:", error);
    container.style.boxShadow = originalBoxShadow;
    view.style.cssText = originalStyle;
    if (originalHidden) view.classList.add('hidden');
  }
}

// Send score notification to Telegram chat via Bot API
async function sendTelegramNotification(studentName, tense, scoreFraction, percentage, timeString) {
  const token = '8993389047:AAG8FpaYAZMHMF3hOV2BpLQKM_0venimdBI';
  let chatId = null;
  
  if (tg && tg.initDataUnsafe && tg.initDataUnsafe.user) {
    chatId = tg.initDataUnsafe.user.id;
  }
  
  if (!chatId) {
    console.log("No telegram chat id available. Skipping notifications.");
    return;
  }

  const text = `🎓 *English24h Test Result* 🎓\n\n👤 *Student:* ${studentName}\n📚 *Tense:* ${tense}\n✅ *Score:* ${scoreFraction} (${percentage}%)\n⏱ *Time Taken:* ${timeString}\n\nKeep up the great work! 🚀`;

  try {
    await fetch(`https://api.telegram.org/bot${token}/sendMessage`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        chat_id: chatId,
        text: text,
        parse_mode: 'Markdown'
      })
    });
  } catch (error) {
    console.error("Telegram bot API message error:", error);
  }
}

// Certificate Generator Helpers
function showCertificate() {
  const name = state.studentName;
  const tense = state.currentQuizTense;
  
  const totalQuestions = state.quizQuestions.length;
  const percentage = Math.round((state.scoreCount / totalQuestions) * 100);
  
  let grade = 'D';
  if (percentage >= 90) grade = 'A';
  else if (percentage >= 75) grade = 'B';
  else if (percentage >= 60) grade = 'C';
  
  document.getElementById('cert-student-name').textContent = name;
  document.getElementById('cert-tense-name').textContent = tense;
  document.getElementById('cert-grade').textContent = grade;
  
  const now = new Date();
  document.getElementById('cert-date-text').textContent = getOrdinalDateString(now);
  
  generateGoldSeal();
  showView('view-certificate');
}

function getOrdinalDateString(date) {
  const day = date.getDate();
  const months = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"
  ];
  const month = months[date.getMonth()];
  const year = date.getFullYear();
  
  let suffix = "th";
  if (day === 1 || day === 21 || day === 31) suffix = "st";
  else if (day === 2 || day === 22) suffix = "nd";
  else if (day === 3 || day === 23) suffix = "rd";
  
  return `${day}${suffix} day of ${month}, ${year}`;
}

function generateGoldSeal() {
  const sealSvg = document.getElementById('cert-seal-svg');
  if (!sealSvg) return;
  
  const center = 60;
  const points = [];
  const numPoints = 32;
  for (let i = 0; i < numPoints * 2; i++) {
    const angle = (i * Math.PI) / numPoints;
    const r = (i % 2 === 0) ? 50 : 45;
    const x = center + r * Math.cos(angle);
    const y = center + r * Math.sin(angle);
    points.push(`${x.toFixed(1)},${y.toFixed(1)}`);
  }
  
  sealSvg.innerHTML = `
    <defs>
      <linearGradient id="gold-seal-grad" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#fff2a3" />
        <stop offset="20%" stop-color="#f5d061" />
        <stop offset="40%" stop-color="#e6b325" />
        <stop offset="60%" stop-color="#fff2a3" />
        <stop offset="80%" stop-color="#d4af37" />
        <stop offset="100%" stop-color="#8a6f27" />
      </linearGradient>
      <filter id="seal-shadow" x="-10%" y="-10%" width="120%" height="120%">
        <feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#000000" flood-opacity="0.3"/>
      </filter>
    </defs>
    <polygon points="${points.join(' ')}" fill="url(#gold-seal-grad)" filter="url(#seal-shadow)" />
    <circle cx="60" cy="60" r="38" fill="url(#gold-seal-grad)" stroke="#fff" stroke-width="1" stroke-opacity="0.4" />
    <circle cx="60" cy="60" r="34" fill="none" stroke="#b8932d" stroke-width="1.5" stroke-dasharray="3 2" />
    <circle cx="60" cy="60" r="30" fill="none" stroke="#b8932d" stroke-width="1" />
    <path d="M60 45 L63 54 L72 54 L65 59 L67 68 L60 63 L53 68 L55 59 L48 54 L57 54 Z" fill="#b8932d" />
  `;
}

async function saveCertificatePDF() {
  const container = document.getElementById('certificate-container');
  showToast("Generating PDF certificate. Please wait...", "info");

  const originalBoxShadow = container.style.boxShadow;
  container.style.boxShadow = 'none';

  try {
    const canvas = await html2canvas(container, {
      scale: 2.5, // High resolution scale for clear A4 size printing
      width: 840, // Force uncropped width (matches A4 landscape container)
      height: 594, // Force uncropped height (matches A4 landscape container)
      scrollX: 0,
      scrollY: 0,
      windowWidth: 840,
      windowHeight: 594,
      useCORS: true,
      allowTaint: true,
      logging: false,
      backgroundColor: '#ffffff'
    });

    container.style.boxShadow = originalBoxShadow;

    const pngDataUrl = canvas.toDataURL('image/png');

    // Generate PDF using jsPDF
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({
      orientation: 'landscape',
      unit: 'px',
      format: [840, 594]
    });
    pdf.addImage(pngDataUrl, 'PNG', 0, 0, 840, 594);
    
    const pdfDataUrl = pdf.output('datauristring');
    const safeTense = state.currentQuizTense.replace(/\s+/g, '_');

    // 1. Save PDF directly to browser localStorage
    try {
      localStorage.setItem(`english24h_saved_pdf_${safeTense}`, pdfDataUrl);
    } catch (e) {
      console.warn("LocalStorage quota exceeded. Clearing old certs...");
      clearOldStoredCerts();
      try {
        localStorage.setItem(`english24h_saved_pdf_${safeTense}`, pdfDataUrl);
      } catch (err) {
        console.error("Could not save PDF to localStorage:", err);
      }
    }

    // 2. Download / Save PDF file to student's local device storage
    const safeName = state.studentName.trim().replace(/[^a-zA-Z0-9]/g, '_');
    const safeTenseFile = state.currentQuizTense.trim().replace(/[^a-zA-Z0-9]/g, '_');
    const fileName = `English24h_Certificate_${safeName}_${safeTenseFile}.pdf`;
    
    // Trigger file download natively
    pdf.save(fileName);
    
    // Fallback for Mobile webview environments (like Telegram Mini App on mobile)
    if (tg && (tg.platform === 'ios' || tg.platform === 'android')) {
      showToast("PDF saved to local storage! Opening document preview...", "success");
      
      // Auto-trigger bot message delivery for direct mobile downloads
      if (tg.initDataUnsafe && tg.initDataUnsafe.user && tg.initDataUnsafe.user.id) {
        await sendCertToTelegram(pdfDataUrl, 'pdf');
      }
      
      setTimeout(() => {
        const win = window.open();
        if (win) {
          win.document.write(`<iframe src="${pdfDataUrl}" frameborder="0" style="border:0; top:0px; left:0px; bottom:0px; right:0px; width:100%; height:100%;" allowfullscreen></iframe>`);
        } else {
          location.href = pdfDataUrl;
        }
      }, 1200);
    } else {
      showToast("PDF saved to local storage & downloaded!", "success");
    }

  } catch (error) {
    console.error("PDF generation failed:", error);
    showToast("Failed to generate PDF.", "error");
    container.style.boxShadow = originalBoxShadow;
  }
}

// Clear older certificate assets if LocalStorage exceeds quota
function clearOldStoredCerts() {
  for (let i = 0; i < localStorage.length; i++) {
    const key = localStorage.key(i);
    if (key && key.startsWith('english24h_saved_pdf_')) {
      localStorage.removeItem(key);
    }
  }
}

// Global function to review past certificates
window.viewHistoryCertificate = function(tense, percent, dateString) {
  state.currentQuizTense = tense;
  
  // Reconstruct score counts
  state.quizQuestions = new Array(5);
  state.scoreCount = Math.round((percent / 100) * 5);
  
  document.getElementById('cert-student-name').textContent = state.studentName;
  document.getElementById('cert-tense-name').textContent = tense;
  
  let grade = 'C';
  if (percent >= 90) grade = 'A';
  else if (percent >= 75) grade = 'B';
  
  document.getElementById('cert-grade').textContent = grade;
  document.getElementById('cert-date-text').textContent = dateString;
  
  generateGoldSeal();
  showView('view-certificate');
};

let currentGeneratedDataUrl = ''; // Tracks the latest generated base64 PNG dataUrl

async function downloadCertificatePNG() {
  const container = document.getElementById('certificate-container');
  showToast("Generating certificate PNG. Please wait...", "info");

  const originalBoxShadow = container.style.boxShadow;
  container.style.boxShadow = 'none';

  try {
    const canvas = await html2canvas(container, {
      scale: 2.5, // High resolution scale for clear A4 size printing
      width: 840, // Force uncropped width (matches A4 landscape container)
      height: 594, // Force uncropped height (matches A4 landscape container)
      scrollX: 0,
      scrollY: 0,
      windowWidth: 840,
      windowHeight: 594,
      useCORS: true,
      allowTaint: true,
      logging: false,
      backgroundColor: '#ffffff'
    });

    container.style.boxShadow = originalBoxShadow;

    const dataUrl = canvas.toDataURL('image/png');
    currentGeneratedDataUrl = dataUrl;

    // Set preview image for mobile devices
    document.getElementById('cert-preview-img').src = dataUrl;
    
    // Auto save to localStorage (both PNG and PDF as base64 string)
    saveCertificateToLocalStorage(state.currentQuizTense, dataUrl);

    // Show Mobile Preview Overlay
    document.getElementById('modal-image-preview').classList.remove('hidden');

    // Trigger standard desktop download fallback
    const link = document.createElement('a');
    const safeName = state.studentName.trim().replace(/[^a-zA-Z0-9]/g, '_');
    const safeTense = state.currentQuizTense.trim().replace(/[^a-zA-Z0-9]/g, '_');
    link.download = `English24h_Certificate_${safeName}_${safeTense}.png`;
    link.href = dataUrl;
    link.click();

    // Send directly to Telegram chat if inside Telegram Mini App
    if (tg && tg.initDataUnsafe && tg.initDataUnsafe.user && tg.initDataUnsafe.user.id) {
      await sendCertToTelegram(dataUrl, 'png');
    }

    showToast("Certificate processed successfully!", "success");
  } catch (error) {
    console.error("html2canvas generation failed:", error);
    showToast("Failed to generate image. Try Save PDF version.", "error");
    container.style.boxShadow = originalBoxShadow;
  }
}

// Save certificate PNG and PDF directly to localStorage
function saveCertificateToLocalStorage(tense, pngDataUrl) {
  const safeTense = tense.replace(/\s+/g, '_');
  
  // 1. Save PNG to localStorage
  try {
    localStorage.setItem(`english24h_saved_png_${safeTense}`, pngDataUrl);
  } catch (e) {
    console.warn("LocalStorage quota exceeded for PNG. Clearing old ones...");
    clearOldStoredCerts();
    try {
      localStorage.setItem(`english24h_saved_png_${safeTense}`, pngDataUrl);
    } catch (err) {
      console.error("Could not save PNG to localStorage:", err);
    }
  }

  // 2. Convert to PDF using jsPDF and save PDF to localStorage
  try {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({
      orientation: 'landscape',
      unit: 'px',
      format: [840, 594]
    });
    pdf.addImage(pngDataUrl, 'PNG', 0, 0, 840, 594);
    const pdfDataUrl = pdf.output('datauristring');
    localStorage.setItem(`english24h_saved_pdf_${safeTense}`, pdfDataUrl);
  } catch (pdfErr) {
    console.error("Failed to generate and save PDF to localStorage:", pdfErr);
  }
}

// Action for explicit manual save button on modal
function saveToLocalStorageAction() {
  if (currentGeneratedDataUrl) {
    saveCertificateToLocalStorage(state.currentQuizTense, currentGeneratedDataUrl);
    showToast("Certificate files successfully saved to local storage!", "success");
  } else {
    showToast("No generated certificate found to save.", "error");
  }
}

// Dynamic renderer for the dashboard certificate gallery
function renderCertificatesGallery() {
  const container = document.getElementById('certs-gallery-grid');
  container.innerHTML = '';
  
  // Filter scores to find passing attempts. Keep only the highest attempt for each tense!
  const passingCerts = {};
  state.scoreLogs.forEach(log => {
    if (log.scorePercent >= 60) {
      if (!passingCerts[log.tense] || log.scorePercent > passingCerts[log.tense].scorePercent) {
        passingCerts[log.tense] = log;
      }
    }
  });
  
  const certList = Object.values(passingCerts);
  
  if (certList.length === 0) {
    container.innerHTML = '<div class="text-center" style="padding: 30px; color: var(--text-secondary);">You haven\'t earned any certificates yet. Complete a quiz with a score of 60% or more to earn one!</div>';
    return;
  }
  
  certList.forEach(cert => {
    const card = document.createElement('div');
    card.className = 'btn-tense';
    card.style.height = 'auto';
    card.style.padding = '16px';
    card.style.display = 'flex';
    card.style.flexDirection = 'row';
    card.style.alignItems = 'center';
    card.style.justifyContent = 'space-between';
    card.style.background = 'rgba(255, 215, 0, 0.05)';
    card.style.border = '1px solid rgba(212, 175, 55, 0.3)';
    
    let grade = 'C';
    if (cert.scorePercent >= 90) grade = 'A';
    else if (cert.scorePercent >= 75) grade = 'B';
    
    card.innerHTML = `
      <div style="text-align: left;">
        <h4 style="color: #fff; margin-bottom: 4px; font-size: 1rem;"><i class="fa-solid fa-award" style="color: #ffd700; margin-right: 6px;"></i> ${cert.tense}</h4>
        <p style="font-size: 0.78rem; color: var(--text-secondary); margin: 0;">Awarded Grade ${grade} (${cert.scorePercent}%) on ${cert.date}</p>
      </div>
      <button class="btn btn-sm btn-primary" onclick="viewHistoryCertificate('${cert.tense}', ${cert.scorePercent}, '${cert.date}')" style="background: var(--gold-gradient); color: #0b0f19; font-weight: 600; padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; box-shadow: 0 4px 10px rgba(212, 175, 55, 0.25); border: none;">
        <i class="fa-solid fa-eye"></i> View
      </button>
    `;
    container.appendChild(card);
  });
}

async function handleCustomizeSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const formData = new FormData(form);
  
  showToast("Uploading assets...", "info");
  
  try {
    const response = await fetch('api.php?action=upload_cert_assets', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();
    
    if (data.success) {
      showToast("Certificate template updated successfully!", "success");
      form.reset();
      await loadCertificateAssets(); // Reload custom images
    } else {
      showToast(data.error || "Upload failed.", "error");
    }
  } catch (err) {
    console.error("Asset upload error:", err);
    showToast("Server connection error during upload.", "error");
  }
}

async function loadCertificateAssets() {
  try {
    const response = await fetch('api.php?action=get_cert_assets');
    const data = await response.json();
    
    if (data.success) {
      // 1. Logo Slot
      const logoImg = document.getElementById('cert-logo-img');
      if (data.logo) {
        logoImg.src = data.logo;
        logoImg.classList.remove('hidden');
      } else {
        logoImg.src = '';
        logoImg.classList.add('hidden');
      }
      
      // 2. Border Frame
      const frame = document.getElementById('cert-gold-frame');
      if (data.border) {
        frame.style.border = 'none';
        frame.style.backgroundImage = `url('${data.border}')`;
        frame.style.backgroundSize = '100% 100%';
      } else {
        frame.style.border = '2px solid #c5a059';
        frame.style.backgroundImage = 'none';
      }
      
      // 3. Signature
      const sigImg = document.getElementById('cert-signature-img');
      const customSigContainer = document.getElementById('cert-custom-signature-container');
      const defaultSigContainer = document.getElementById('cert-default-signature');
      
      if (data.signature) {
        sigImg.src = data.signature;
        customSigContainer.classList.remove('hidden');
        defaultSigContainer.classList.add('hidden');
      } else {
        sigImg.src = '';
        customSigContainer.classList.add('hidden');
        defaultSigContainer.classList.remove('hidden');
      }
    }
  } catch (err) {
    console.error("Error loading certificate assets:", err);
  }
}

window.resetCertAsset = async function(target) {
  if (confirm(`Are you sure you want to reset the custom ${target}?`)) {
    try {
      const response = await fetch(`api.php?action=reset_cert_assets&target=${target}`);
      const data = await response.json();
      if (data.success) {
        showToast(`Custom ${target} has been reset.`, "success");
        await loadCertificateAssets();
      } else {
        showToast(data.error || "Reset failed.", "error");
      }
    } catch (err) {
      console.error(err);
      showToast("Server connection error.", "error");
    }
  }
};

// ADMIN PANEL LOGIC (MySQL API Sync)
async function handleAdminLogin(e) {
  e.preventDefault();
  const userField = document.getElementById('admin-username').value.trim();
  const passField = document.getElementById('admin-password').value.trim();

  try {
    const response = await fetch('api.php?action=admin_login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: userField, password: passField })
    });
    const data = await response.json();
    
    if (data.success) {
      showToast("Access Granted!", "success");
      document.getElementById('admin-username').value = '';
      document.getElementById('admin-password').value = '';
      showView('view-admin-dashboard');
    } else {
      showToast(data.error || "Invalid credentials. Try again.", "error");
    }
  } catch (err) {
    console.error(err);
    showToast("Server connection error.", "error");
  }
}

function handleAdminLogout() {
  showToast("Logged out of Admin Portal.", "info");
  if (state.studentName) {
    showView('view-dashboard');
  } else {
    showView('view-welcome');
  }
}

function switchAdminTab(tabName) {
  const btnQuestions = document.getElementById('tab-questions');
  const btnScores = document.getElementById('tab-scores');
  const btnCustomize = document.getElementById('tab-customize');
  const divQuestions = document.getElementById('admin-tab-content-questions');
  const divScores = document.getElementById('admin-tab-content-scores');
  const divCustomize = document.getElementById('admin-tab-content-customize');

  btnQuestions.classList.remove('active');
  btnScores.classList.remove('active');
  btnCustomize.classList.remove('active');
  divQuestions.classList.add('hidden');
  divScores.classList.add('hidden');
  divCustomize.classList.add('hidden');

  if (tabName === 'questions') {
    btnQuestions.classList.add('active');
    divQuestions.classList.remove('hidden');
    renderAdminQuestionsList();
  } else if (tabName === 'scores') {
    btnScores.classList.add('active');
    divScores.classList.remove('hidden');
    renderAdminScoreLogs();
  } else if (tabName === 'customize') {
    btnCustomize.classList.add('active');
    divCustomize.classList.remove('hidden');
  }
}

async function loadAdminDashboardData() {
  try {
    // 1. Fetch all questions from database
    let response = await fetch('api.php?action=get_all_questions');
    let data = await response.json();
    if (data.success) {
      state.questions = data.questions;
    }
    
    // 2. Fetch all student logs from database
    response = await fetch('api.php?action=get_student_logs');
    data = await response.json();
    if (data.success) {
      state.scoreLogs = data.logs;
    }
    
    renderAdminQuestionsList();
    renderAdminScoreLogs();
  } catch (err) {
    console.error("Error loading admin dashboard data:", err);
    showToast("Error loading server data.", "error");
  }
}

function renderAdminQuestionsList() {
  const filterTense = document.getElementById('admin-filter-tense').value;
  const listContainer = document.getElementById('admin-questions-list');
  listContainer.innerHTML = '';

  let filtered = state.questions;
  if (filterTense !== 'all') {
    filtered = state.questions.filter(q => q.tense === filterTense);
  }

  if (filtered.length === 0) {
    listContainer.innerHTML = '<div class="glass-panel text-center" style="padding:30px; color:var(--text-secondary);">No questions found. Click "Add QCM" to create one.</div>';
    return;
  }

  const displayList = [...filtered].reverse();

  displayList.forEach(q => {
    const card = document.createElement('div');
    card.className = 'admin-question-card glass-panel';
    
    let optionsHtml = '';
    q.options.forEach((opt, idx) => {
      const isCorrect = idx === q.correctIdx;
      optionsHtml += `<div class="admin-q-opt ${isCorrect ? 'correct-opt' : ''}">${String.fromCharCode(65 + idx)}. ${opt}</div>`;
    });

    card.innerHTML = `
      <div class="q-card-header">
        <span class="q-badge">${q.tense}</span>
        <div class="q-actions">
          <button class="btn-q-action edit" onclick="editQuestionTrigger('${q.id}')" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
          <button class="btn-q-action delete" onclick="deleteQuestionTrigger('${q.id}')" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
        </div>
      </div>
      <div class="admin-q-text">${q.question}</div>
      <div class="admin-q-options">
        ${optionsHtml}
      </div>
    `;

    listContainer.appendChild(card);
  });
}

function renderAdminScoreLogs() {
  const rowsContainer = document.getElementById('admin-score-logs-rows');
  rowsContainer.innerHTML = '';

  if (state.scoreLogs.length === 0) {
    rowsContainer.innerHTML = '<tr><td colspan="4" class="text-center">No student activities recorded yet.</td></tr>';
    return;
  }

  state.scoreLogs.forEach(log => {
    const tr = document.createElement('tr');
    let badgeClass = 'score-low';
    if (log.scorePercent >= 80) badgeClass = 'score-high';
    else if (log.scorePercent >= 50) badgeClass = 'score-medium';

    tr.innerHTML = `
      <td style="font-weight: 600;">${log.studentName}</td>
      <td style="font-weight: 500;">${log.tense}</td>
      <td><span class="score-badge ${badgeClass}">${log.scorePercent}% (${log.scoreFraction})</span></td>
      <td style="font-size: 0.8rem; color: var(--text-secondary);">${log.date}</td>
    `;
    rowsContainer.appendChild(tr);
  });
}

async function clearAdminScoreLogs() {
  if (confirm("Are you sure you want to clear all student activity score logs? This cannot be undone.")) {
    try {
      const response = await fetch('api.php?action=clear_student_logs');
      const data = await response.json();
      if (data.success) {
        state.scoreLogs = [];
        showToast("Activity score logs cleared.", "success");
        renderAdminScoreLogs();
      } else {
        showToast(data.error || "Failed to clear logs.", "error");
      }
    } catch (err) {
      console.error(err);
      showToast("Server connection error.", "error");
    }
  }
}

function showQuestionModal(questionId = null) {
  const modal = document.getElementById('modal-question-editor');
  const title = document.getElementById('modal-editor-title');
  const form = document.getElementById('question-editor-form');
  
  form.reset();
  
  if (questionId) {
    title.textContent = "Edit QCM Question";
    const q = state.questions.find(item => item.id == questionId);
    if (q) {
      document.getElementById('edit-question-id').value = q.id;
      document.getElementById('edit-question-tense').value = q.tense;
      document.getElementById('edit-question-text').value = q.question;
      document.getElementById('edit-option-0').value = q.options[0];
      document.getElementById('edit-option-1').value = q.options[1];
      document.getElementById('edit-option-2').value = q.options[2];
      document.getElementById('edit-option-3').value = q.options[3];
      document.getElementById('edit-correct-idx').value = q.correctIdx;
    }
  } else {
    title.textContent = "Add QCM Question";
    document.getElementById('edit-question-id').value = '';
    const filterTense = document.getElementById('admin-filter-tense').value;
    if (filterTense !== 'all') {
      document.getElementById('edit-question-tense').value = filterTense;
    }
  }
  
  modal.classList.remove('hidden');
}

function hideQuestionModal() {
  document.getElementById('modal-question-editor').classList.add('hidden');
}

async function saveQuestion(e) {
  e.preventDefault();
  
  const qId = document.getElementById('edit-question-id').value;
  const tense = document.getElementById('edit-question-tense').value;
  const questionText = document.getElementById('edit-question-text').value.trim();
  const opt0 = document.getElementById('edit-option-0').value.trim();
  const opt1 = document.getElementById('edit-option-1').value.trim();
  const opt2 = document.getElementById('edit-option-2').value.trim();
  const opt3 = document.getElementById('edit-option-3').value.trim();
  const correctIdx = parseInt(document.getElementById('edit-correct-idx').value);

  const questionPayload = {
    id: qId ? parseInt(qId) : null,
    tense,
    question: questionText,
    options: [opt0, opt1, opt2, opt3],
    correctIdx
  };

  try {
    const response = await fetch('api.php?action=save_question', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(questionPayload)
    });
    const data = await response.json();
    
    if (data.success) {
      showToast(qId ? "Question updated successfully!" : "New Question added successfully!", "success");
      hideQuestionModal();
      await loadAdminDashboardData();
    } else {
      showToast(data.error || "Failed to save question.", "error");
    }
  } catch (err) {
    console.error(err);
    showToast("Server connection error.", "error");
  }
}

window.editQuestionTrigger = function(id) {
  showQuestionModal(id);
};

window.deleteQuestionTrigger = async function(id) {
  if (confirm("Are you sure you want to delete this question?")) {
    try {
      const response = await fetch('api.php?action=delete_question', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: parseInt(id) })
      });
      const data = await response.json();
      if (data.success) {
        showToast("Question deleted.", "info");
        await loadAdminDashboardData();
      } else {
        showToast(data.error || "Failed to delete question.", "error");
      }
    } catch (err) {
      console.error(err);
      showToast("Server connection error.", "error");
    }
  }
};

async function loadDashboardTenses() {
  try {
    const response = await fetch('api.php?action=get_tenses');
    const data = await response.json();
    if (!data.success) return;

    const tensesList = data.tenses;
    const container = document.getElementById('dashboard-tenses-container');
    if (!container) return;
    container.innerHTML = '';

    // Group tenses into categories
    const categories = {
      "Present Tenses": {
        theme: "present-theme",
        icon: "fa-clock",
        btnClass: "present-btn",
        items: []
      },
      "Past Tenses": {
        theme: "past-theme",
        icon: "fa-history",
        btnClass: "past-btn",
        items: []
      },
      "Future Tenses": {
        theme: "future-theme",
        icon: "fa-paper-plane",
        btnClass: "future-btn",
        items: []
      },
      "Other Grammar Tests": {
        theme: "custom-theme",
        icon: "fa-graduation-cap",
        btnClass: "custom-btn",
        items: []
      }
    };

    // Populate categories dynamically based on query results
    tensesList.forEach(tense => {
      const tLower = tense.toLowerCase();
      if (tLower.includes("present")) {
        categories["Present Tenses"].items.push(tense);
      } else if (tLower.includes("past")) {
        categories["Past Tenses"].items.push(tense);
      } else if (tLower.includes("future")) {
        categories["Future Tenses"].items.push(tense);
      } else {
        categories["Other Grammar Tests"].items.push(tense);
      }
    });

    // Render HTML cards
    Object.keys(categories).forEach(catName => {
      const cat = categories[catName];
      if (cat.items.length === 0) return; // Skip empty categories

      const card = document.createElement('div');
      card.className = 'tense-category-card';
      
      let buttonsHtml = '';
      cat.items.forEach(tense => {
        buttonsHtml += `
          <button class="btn-tense ${cat.btnClass}" data-tense="${tense}">
            <span class="tense-title">${tense}</span>
            <span class="tense-meta"><i class="fa-solid fa-circle-play"></i> Practice</span>
          </button>
        `;
      });

      card.innerHTML = `
        <div class="category-header ${cat.theme}">
          <i class="fa-solid ${cat.icon}"></i>
          <h3>${catName}</h3>
        </div>
        <div class="category-buttons">
          ${buttonsHtml}
        </div>
      `;
      container.appendChild(card);
    });

    // Re-bind click handlers to dynamic buttons
    const buttons = document.querySelectorAll('.btn-tense[data-tense]');
    buttons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        const tense = e.currentTarget.getAttribute('data-tense');
        startQuiz(tense);
      });
    });

  } catch (err) {
    console.error("Error loading dashboard categories:", err);
  }
}

async function loadSettings() {
  try {
    const response = await fetch('api.php?action=get_settings');
    const data = await response.json();
    if (data.success && data.settings) {
      state.quizTimer = parseInt(data.settings.quiz_timer) || 30;
      state.quizQuestionsCount = parseInt(data.settings.quiz_questions_count) || 5;
      
      // Update form values
      const timerInput = document.getElementById('settings-quiz-timer');
      const countInput = document.getElementById('settings-quiz-count');
      if (timerInput) timerInput.value = state.quizTimer;
      if (countInput) countInput.value = state.quizQuestionsCount;
    }
  } catch (err) {
    console.error("Error loading app settings:", err);
  }
}

async function handleSettingsSubmit(e) {
  e.preventDefault();
  const timer = parseInt(document.getElementById('settings-quiz-timer').value);
  const count = parseInt(document.getElementById('settings-quiz-count').value);
  
  showToast("Saving settings...", "info");
  
  try {
    const response = await fetch('api.php?action=save_settings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        quiz_timer: timer,
        quiz_questions_count: count
      })
    });
    const data = await response.json();
    if (data.success) {
      state.quizTimer = timer;
      state.quizQuestionsCount = count;
      showToast("Configuration settings saved successfully!", "success");
    } else {
      showToast(data.error || "Failed to save settings.", "error");
    }
  } catch (err) {
    console.error(err);
    showToast("Server connection error.", "error");
  }
}

async function sendCertToTelegram(base64Data, fileType) {
  if (!tg || !tg.initDataUnsafe || !tg.initDataUnsafe.user) return;
  const chatId = tg.initDataUnsafe.user.id;
  
  showToast(`Sending ${fileType.toUpperCase()} file to your chat...`, "info");
  
  try {
    const response = await fetch('api.php?action=send_cert_telegram', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        chat_id: chatId,
        file_type: fileType,
        base64_data: base64Data,
        student_name: state.studentName,
        tense: state.currentQuizTense
      })
    });
    const data = await response.json();
    if (data.ok) {
      showToast(`Certificate ${fileType.toUpperCase()} sent to your Telegram inbox!`, "success");
    } else {
      console.warn("Telegram delivery error:", data);
      showToast("Could not send to Telegram chat.", "warning");
    }
  } catch (err) {
    console.error("Error sending cert to Telegram:", err);
  }
}
