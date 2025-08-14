<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Students Dashboard</title>
    <!-- Tailwind CSS for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Custom styles to match the design */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        /* Style for the status dots */
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        .status-active { background-color: #28a745; }
        .status-pending { background-color: #ffc107; }
        .status-missed { background-color: #dc3545; }
        
        /* Modal styles */
        .modal-backdrop {
            transition: opacity 0.3s ease;
        }
        .modal-content {
            transition: transform 0.3s ease;
        }

        /* Simple spinner for loading state */
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border-left-color: #4f46e5;
            animation: spin 1s ease infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <!-- Main Container -->
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">

        <!-- Header Section -->
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">My Students</h1>
            <p class="text-gray-600 mt-1">Manage your assigned students and track their progress</p>
        </header>

        <!-- Filters and Search Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div class="flex flex-col sm:flex-row gap-4">
                <!-- Subject Filter Dropdown -->
                <div class="relative">
                    <select class="appearance-none w-full sm:w-auto bg-white border border-gray-300 rounded-lg py-2 pl-3 pr-10 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option>All Subjects</option>
                        <option>Mathematics</option>
                        <option>Physics</option>
                        <option>English Literature</option>
                        <option>Calculus</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                    </div>
                </div>
                <!-- Package Filter Dropdown -->
                <div class="relative">
                    <select class="appearance-none w-full sm:w-auto bg-white border border-gray-300 rounded-lg py-2 pl-3 pr-10 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option>All Packages</option>
                        <option>Standard (2/week)</option>
                        <option>Premium (3/week)</option>
                        <option>Basic (1/week)</option>
                    </select>
                     <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                    </div>
                </div>
            </div>
            <!-- Search Input -->
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                </span>
                <input type="text" placeholder="Search students..." class="w-full md:w-64 bg-white border border-gray-300 rounded-lg py-2 pl-10 pr-4 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        </div>

        <!-- Student Cards Grid -->
        <div id="student-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">

            <!-- Student Card: Emma Johnson -->
            <div class="student-card bg-white rounded-xl shadow-md overflow-hidden p-6 flex flex-col justify-between"
                 data-name="Emma Johnson"
                 data-details="Grade 10 • Mathematics"
                 data-status="Active"
                 data-package="Standard (2/week)"
                 data-lesson="Today, 3:00 PM"
                 data-progress="12/20 lessons"
                 data-progress-percent="60">
                <div>
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center"><svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg></div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Emma Johnson</h3>
                                <p class="text-sm text-gray-500">Grade 10 • Mathematics</p>
                            </div>
                        </div>
                        <span class="flex items-center text-sm font-medium text-gray-700"><span class="status-dot status-active"></span>Active</span>
                    </div>
                    <div class="mt-6 space-y-4 text-sm">
                        <p><span class="font-medium text-gray-500 w-24 inline-block">Package:</span> Standard (2/week)</p>
                        <p><span class="font-medium text-gray-500 w-24 inline-block">Next Lesson:</span> Today, 3:00 PM</p>
                        <div>
                            <div class="flex justify-between items-center mb-1"><span class="font-medium text-gray-500">Progress</span><span class="text-gray-600 font-medium">12/20 lessons</span></div>
                            <div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-indigo-600 h-2 rounded-full" style="width: 60%;"></div></div>
                            <p class="text-right text-xs text-gray-500 mt-1">60% complete</p>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex gap-3">
                    <button class="flex-1 bg-gray-800 hover:bg-gray-900 text-white font-semibold py-2 px-4 rounded-lg">View Profile</button>
                    <button class="ai-assistant-btn flex-1 bg-indigo-100 hover:bg-indigo-200 text-indigo-800 font-semibold py-2 px-4 rounded-lg">✨ AI Assistant</button>
                </div>
            </div>

            <!-- Student Card: Michael Chen -->
            <div class="student-card bg-white rounded-xl shadow-md overflow-hidden p-6 flex flex-col justify-between"
                 data-name="Michael Chen"
                 data-details="Grade 11 • Physics, Chemistry"
                 data-status="Pending"
                 data-package="Premium (3/week)"
                 data-lesson="Tomorrow, 10:00 AM"
                 data-progress="8/24 lessons"
                 data-progress-percent="33">
                <div>
                     <div class="flex items-start justify-between">
                         <div class="flex items-center gap-4">
                             <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center"><svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg></div>
                             <div>
                                 <h3 class="text-lg font-semibold text-gray-900">Michael Chen</h3>
                                 <p class="text-sm text-gray-500">Grade 11 • Physics, Chemistry</p>
                             </div>
                         </div>
                         <span class="flex items-center text-sm font-medium text-gray-700"><span class="status-dot status-pending"></span>Pending</span>
                     </div>
                     <div class="mt-6 space-y-4 text-sm">
                         <p><span class="font-medium text-gray-500 w-24 inline-block">Package:</span> Premium (3/week)</p>
                         <p><span class="font-medium text-gray-500 w-24 inline-block">Next Lesson:</span> Tomorrow, 10:00 AM</p>
                         <div>
                             <div class="flex justify-between items-center mb-1"><span class="font-medium text-gray-500">Progress</span><span class="text-gray-600 font-medium">8/24 lessons</span></div>
                             <div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-indigo-600 h-2 rounded-full" style="width: 33%;"></div></div>
                             <p class="text-right text-xs text-gray-500 mt-1">33% complete</p>
                         </div>
                     </div>
                </div>
                <div class="mt-6 flex gap-3">
                     <button class="flex-1 bg-gray-800 hover:bg-gray-900 text-white font-semibold py-2 px-4 rounded-lg">View Profile</button>
                    <button class="ai-assistant-btn flex-1 bg-indigo-100 hover:bg-indigo-200 text-indigo-800 font-semibold py-2 px-4 rounded-lg">✨ AI Assistant</button>
                </div>
            </div>

            <!-- Student Card: Sarah Williams -->
             <div class="student-card bg-white rounded-xl shadow-md overflow-hidden p-6 flex flex-col justify-between"
                  data-name="Sarah Williams"
                  data-details="Grade 9 • English Literature"
                  data-status="Active"
                  data-package="Basic (1/week)"
                  data-lesson="Friday, 2:00 PM"
                  data-progress="15/16 lessons"
                  data-progress-percent="94">
                 <div>
                    <div class="flex items-start justify-between">
                         <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center"><svg xmlns="http="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg></div>
                             <div>
                                 <h3 class="text-lg font-semibold text-gray-900">Sarah Williams</h3>
                                 <p class="text-sm text-gray-500">Grade 9 • English Literature</p>
                             </div>
                         </div>
                         <span class="flex items-center text-sm font-medium text-gray-700"><span class="status-dot status-active"></span>Active</span>
                     </div>
                     <div class="mt-6 space-y-4 text-sm">
                         <p><span class="font-medium text-gray-500 w-24 inline-block">Package:</span> Basic (1/week)</p>
                         <p><span class="font-medium text-gray-500 w-24 inline-block">Next Lesson:</span> Friday, 2:00 PM</p>
                         <div>
                             <div class="flex justify-between items-center mb-1"><span class="font-medium text-gray-500">Progress</span><span class="text-gray-600 font-medium">15/16 lessons</span></div>
                             <div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-indigo-600 h-2 rounded-full" style="width: 94%;"></div></div>
                             <p class="text-right text-xs text-gray-500 mt-1">94% complete</p>
                         </div>
                     </div>
                </div>
                <div class="mt-6 flex gap-3">
                     <button class="flex-1 bg-gray-800 hover:bg-gray-900 text-white font-semibold py-2 px-4 rounded-lg">View Profile</button>
                    <button class="ai-assistant-btn flex-1 bg-indigo-100 hover:bg-indigo-200 text-indigo-800 font-semibold py-2 px-4 rounded-lg">✨ AI Assistant</button>
                </div>
            </div>
            
            <!-- Student Card: David Rodriguez -->
             <div class="student-card bg-white rounded-xl shadow-md overflow-hidden p-6 flex flex-col justify-between"
                  data-name="David Rodriguez"
                  data-details="Grade 12 • Calculus"
                  data-status="Missed"
                  data-package="Standard (2/week)"
                  data-lesson="Monday, 4:00 PM"
                  data-progress="6/20 lessons"
                  data-progress-percent="30">
                 <div>
                    <div class="flex items-start justify-between">
                         <div class="flex items-center gap-4">
                             <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center"><svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg></div>
                             <div>
                                 <h3 class="text-lg font-semibold text-gray-900">David Rodriguez</h3>
                                 <p class="text-sm text-gray-500">Grade 12 • Calculus</p>
                             </div>
                         </div>
                         <span class="flex items-center text-sm font-medium text-gray-700"><span class="status-dot status-missed"></span>Missed</span>
                     </div>
                     <div class="mt-6 space-y-4 text-sm">
                         <p><span class="font-medium text-gray-500 w-24 inline-block">Package:</span> Standard (2/week)</p>
                         <p><span class="font-medium text-gray-500 w-24 inline-block">Next Lesson:</span> Monday, 4:00 PM</p>
                         <div>
                             <div class="flex justify-between items-center mb-1"><span class="font-medium text-gray-500">Progress</span><span class="text-gray-600 font-medium">6/20 lessons</span></div>
                             <div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-indigo-600 h-2 rounded-full" style="width: 30%;"></div></div>
                             <p class="text-right text-xs text-gray-500 mt-1">30% complete</p>
                         </div>
                     </div>
                </div>
                <div class="mt-6 flex gap-3">
                     <button class="flex-1 bg-gray-800 hover:bg-gray-900 text-white font-semibold py-2 px-4 rounded-lg">View Profile</button>
                    <button class="ai-assistant-btn flex-1 bg-indigo-100 hover:bg-indigo-200 text-indigo-800 font-semibold py-2 px-4 rounded-lg">✨ AI Assistant</button>
                </div>
            </div>
        </div>

        <!-- Pagination Section -->
        <div class="mt-12 flex justify-center items-center gap-2">
            <button class="p-2 rounded-lg hover:bg-gray-200 disabled:text-gray-300" disabled><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg></button>
            <button class="px-4 py-2 rounded-lg bg-gray-800 text-white font-semibold text-sm">1</button>
            <button class="px-4 py-2 rounded-lg hover:bg-gray-200 text-gray-600 font-semibold text-sm">2</button>
            <button class="px-4 py-2 rounded-lg hover:bg-gray-200 text-gray-600 font-semibold text-sm">3</button>
            <button class="p-2 rounded-lg hover:bg-gray-200"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg></button>
        </div>
    </div>
    
    <!-- AI Assistant Modal -->
    <div id="ai-modal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
        <!-- Backdrop -->
        <div id="modal-backdrop" class="modal-backdrop fixed inset-0 bg-black bg-opacity-50"></div>
        
        <!-- Modal Content -->
        <div id="modal-content" class="modal-content bg-white rounded-xl shadow-2xl w-11/12 md:max-w-2xl mx-auto z-10 transform scale-95">
            <!-- Modal Header -->
            <div class="flex justify-between items-center p-4 border-b">
                <h2 class="text-xl font-bold text-gray-800">✨ AI Assistant for <span id="modal-student-name">Student</span></h2>
                <button id="close-modal-btn" class="text-gray-500 hover:text-gray-800">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6">
                <div class="flex flex-col md:flex-row gap-4 mb-6">
                    <button id="draft-report-btn" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">Draft Progress Report</button>
                    <button id="get-talking-points-btn" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg">Get Talking Points</button>
                </div>
                
                <!-- Result Area -->
                <div id="ai-result-container" class="bg-gray-50 rounded-lg p-4 h-64 overflow-y-auto border">
                     <div id="ai-result-placeholder" class="text-gray-500 text-center flex flex-col items-center justify-center h-full">
                        <svg class="w-12 h-12 mb-2 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.898 20.562L16.25 22.5l-.648-1.938a3.375 3.375 0 00-2.655-2.654L11.25 18l1.938-.648a3.375 3.375 0 002.654-2.655L16.25 13l.648 1.938a3.375 3.375 0 002.655 2.654L21.75 18l-1.938.648a3.375 3.375 0 00-2.654 2.655z" /></svg>
                         <p>AI-generated content will appear here.</p>
                     </div>
                     <div id="ai-result-loader" class="hidden items-center justify-center h-full"><div class="spinner"></div></div>
                     <textarea id="ai-result-text" class="w-full h-full bg-transparent rounded-lg focus:outline-none hidden resize-none" readonly></textarea>
                </div>
                 <div id="copy-container" class="text-right mt-2 hidden">
                    <button id="copy-btn" class="text-sm text-indigo-600 hover:text-indigo-800 font-semibold">Copy to Clipboard</button>
                    <span id="copy-feedback" class="text-sm text-green-600 ml-2"></span>
                 </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- Modal Elements ---
            const aiModal = document.getElementById('ai-modal');
            const modalBackdrop = document.getElementById('modal-backdrop');
            const closeModalBtn = document.getElementById('close-modal-btn');
            const modalStudentName = document.getElementById('modal-student-name');
            
            // --- Modal Content Elements ---
            const aiResultContainer = document.getElementById('ai-result-container');
            const placeholder = document.getElementById('ai-result-placeholder');
            const loader = document.getElementById('ai-result-loader');
            const resultText = document.getElementById('ai-result-text');
            const copyContainer = document.getElementById('copy-container');
            const copyBtn = document.getElementById('copy-btn');
            const copyFeedback = document.getElementById('copy-feedback');

            // --- Modal Action Buttons ---
            const draftReportBtn = document.getElementById('draft-report-btn');
            const getTalkingPointsBtn = document.getElementById('get-talking-points-btn');

            let currentStudentData = null;

            // --- Gemini API Call Function ---
            async function callGemini(prompt) {
                // Show loader and hide other content
                loader.style.display = 'flex';
                placeholder.style.display = 'none';
                resultText.style.display = 'none';
                copyContainer.style.display = 'none';
                
                const apiKey = ""; // API key is handled by the environment
                const apiUrl = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=${apiKey}`;
                
                const payload = {
                    contents: [{
                        role: "user",
                        parts: [{ text: prompt }]
                    }]
                };

                try {
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });

                    if (!response.ok) {
                        throw new Error(`API Error: ${response.status} ${response.statusText}`);
                    }

                    const result = await response.json();
                    
                    let text = "Sorry, I couldn't generate a response. Please try again.";
                    if (result.candidates && result.candidates.length > 0 &&
                        result.candidates[0].content && result.candidates[0].content.parts &&
                        result.candidates[0].content.parts.length > 0) {
                        text = result.candidates[0].content.parts[0].text;
                    }

                    // Display the result
                    resultText.value = text;
                    resultText.style.display = 'block';
                    copyContainer.style.display = 'block';
                    
                } catch (error) {
                    console.error("Gemini API call failed:", error);
                    resultText.value = `Error: ${error.message}`;
                    resultText.style.display = 'block';
                } finally {
                    // Hide loader
                    loader.style.display = 'none';
                }
            }


            // --- Modal Open/Close Logic ---
            function openModal() {
                aiModal.classList.remove('hidden');
                setTimeout(() => {
                    modalBackdrop.classList.remove('opacity-0');
                    aiModal.querySelector('#modal-content').classList.remove('scale-95');
                }, 10);
            }

            function closeModal() {
                modalBackdrop.classList.add('opacity-0');
                aiModal.querySelector('#modal-content').classList.add('scale-95');
                setTimeout(() => {
                    aiModal.classList.add('hidden');
                    // Reset modal state
                    placeholder.style.display = 'flex';
                    loader.style.display = 'none';
                    resultText.style.display = 'none';
                    copyContainer.style.display = 'none';
                    copyFeedback.textContent = '';
                }, 300);
            }

            // --- Event Listeners ---
            document.querySelectorAll('.ai-assistant-btn').forEach(button => {
                button.addEventListener('click', (e) => {
                    const card = e.target.closest('.student-card');
                    currentStudentData = card.dataset;
                    modalStudentName.textContent = currentStudentData.name;
                    openModal();
                });
            });

            closeModalBtn.addEventListener('click', closeModal);
            modalBackdrop.addEventListener('click', closeModal);
            
            draftReportBtn.addEventListener('click', () => {
                if (!currentStudentData) return;
                const prompt = `
                    You are a helpful tutor's assistant.
                    Draft a friendly and professional progress report email to the parents of a student.
                    The tone should be encouraging.
                    
                    Here is the student's information:
                    - Name: ${currentStudentData.name}
                    - Details: ${currentStudentData.details}
                    - Status: ${currentStudentData.status}
                    - Tutoring Package: ${currentStudentData.package}
                    - Progress: ${currentStudentData.progress} (${currentStudentData.progressPercent}% complete)
                    - Next Lesson: ${currentStudentData.lesson}
                    
                    The email should include:
                    1. A pleasant opening.
                    2. A summary of their recent progress.
                    3. A mention of their next scheduled lesson.
                    4. An encouraging closing statement.

                    Format the output as a simple text email. Start with a subject line like "Subject: Progress Update for [Student Name]".
                `;
                callGemini(prompt);
            });
            
            getTalkingPointsBtn.addEventListener('click', () => {
                if (!currentStudentData) return;
                const prompt = `
                    You are an experienced and empathetic educational advisor.
                    Provide some practical talking points and strategies for a tutor to discuss with a student.
                    The goal is to motivate the student and address any issues based on their current status.
                    
                    Here is the student's information:
                    - Name: ${currentStudentData.name}
                    - Details: ${currentStudentData.details}
                    - Status: ${currentStudentData.status}
                    - Progress: ${currentStudentData.progress} (${currentStudentData.progressPercent}% complete)
                    
                    Based on their status of "${currentStudentData.status}", generate a few bullet points of advice for the tutor.
                    For example, if the status is "Missed", suggest ways to talk about rescheduling and overcoming obstacles.
                    If the status is "Pending", suggest how to onboard them smoothly.
                    If the status is "Active", suggest ways to maintain momentum or tackle upcoming challenges.

                    Keep the response concise and actionable.
                `;
                callGemini(prompt);
            });

            copyBtn.addEventListener('click', () => {
                // Use a temporary textarea to copy content to avoid issues
                const tempTextArea = document.createElement('textarea');
                tempTextArea.value = resultText.value;
                document.body.appendChild(tempTextArea);
                tempTextArea.select();
                try {
                    document.execCommand('copy');
                    copyFeedback.textContent = 'Copied!';
                    setTimeout(() => { copyFeedback.textContent = ''; }, 2000);
                } catch (err) {
                    console.error('Failed to copy text: ', err);
                    copyFeedback.textContent = 'Failed to copy';
                }
                document.body.removeChild(tempTextArea);
            });
        });
    </script>

</body>
</html>
