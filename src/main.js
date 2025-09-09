// Main application entry point
import './styles.css';
import './pwa.js';
import { initAuth, getCurrentUser } from './auth.js';
import { uploadWaveformFiles, deleteMemoryFiles } from './storage.js';
import { showToast, showLoading, hideLoading, showConfirmDialog, handleError, formatFileSize, validateFileType, validateFileSize } from './utils.js';

// Initialize authentication when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  initAuth();
});

// Make functions available globally for the existing waveform code
window.getCurrentUser = getCurrentUser;
window.uploadWaveformFiles = uploadWaveformFiles;
window.deleteMemoryFiles = deleteMemoryFiles;

// Make utility functions available globally
window.showToast = showToast;
window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.showConfirmDialog = showConfirmDialog;
window.handleError = handleError;
window.formatFileSize = formatFileSize;
window.validateFileType = validateFileType;
window.validateFileSize = validateFileSize;
