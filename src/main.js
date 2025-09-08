// Main application entry point
import { initAuth, getCurrentUser } from './auth.js';
import { uploadWaveformFiles, deleteMemoryFiles } from './storage.js';

// Initialize authentication when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  initAuth();
});

// Make functions available globally for the existing waveform code
window.getCurrentUser = getCurrentUser;
window.uploadWaveformFiles = uploadWaveformFiles;
window.deleteMemoryFiles = deleteMemoryFiles;
