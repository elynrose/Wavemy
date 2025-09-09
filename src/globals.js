/**
 * Global function exports for inline HTML JavaScript compatibility
 * This file ensures all functions called by onclick handlers are available globally
 */

// Import necessary functions from auth module
import { loadUserWaveforms as authLoadUserWaveforms } from './auth.js';

// Re-export functions that need to be globally available
// These functions are defined in the main HTML file but need to be accessible

// Function to delete a memory (this needs to be properly implemented)
window.deleteMemory = async function(memoryId, title, buttonElement) {
  if (!window.showConfirmDialog) {
    alert('Utility functions not loaded');
    return;
  }
  
  const confirmed = await window.showConfirmDialog(
    `Are you sure you want to delete "${title}"? This action cannot be undone.`,
    'Delete Memory'
  );
  
  if (!confirmed) return;
  
  const originalText = buttonElement.textContent;
  buttonElement.textContent = 'Deleting...';
  buttonElement.disabled = true;
  
  try {
    const currentUser = window.getCurrentUser();
    if (!currentUser) {
      throw new Error('Not authenticated');
    }
    
    // Call the server to delete the memory
    const response = await fetch('delete_memory.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `memory_id=${memoryId}&user_id=${encodeURIComponent(currentUser.uid)}`
    });
    
    if (!response.ok) {
      throw new Error('Delete request failed');
    }
    
    const result = await response.json();
    
    if (result.success) {
      // Remove the memory from the UI
      const memoryItem = buttonElement.closest('.waveform-item');
      if (memoryItem) {
        memoryItem.remove();
      }
      
      if (window.showToast) {
        window.showToast(`"${title}" has been deleted.`, 'success');
      }
      
      // Also delete the files from storage if available
      if (window.deleteMemoryFiles && result.files) {
        try {
          await window.deleteMemoryFiles(result.files);
        } catch (storageError) {
          console.warn('Storage cleanup failed:', storageError);
        }
      }
    } else {
      throw new Error(result.error || 'Delete failed');
    }
    
  } catch (error) {
    console.error('Delete error:', error);
    if (window.handleError) {
      window.handleError(error, 'Delete Memory');
    } else {
      alert('Error deleting memory: ' + error.message);
    }
  } finally {
    buttonElement.textContent = originalText;
    buttonElement.disabled = false;
  }
};

// Load user waveforms wrapper
window.loadUserWaveforms = authLoadUserWaveforms;

// Function to show memory modal (defined in main HTML script)
window.showMemoryModal = function(imageUrl, title, qrUrl) {
  // Create modal overlay
  const modal = document.createElement('div');
  modal.className = 'image-modal';
  modal.onclick = (e) => {
    if (e.target === modal) window.closeImageModal();
  };
  
  modal.innerHTML = `
    <div class="image-modal-content">
      <button class="image-modal-close" onclick="closeImageModal()" title="Close">&times;</button>
      <div style="text-align: center; margin-bottom: 16px;">
        <h3 style="color: white; margin: 0 0 8px 0;">${title}</h3>
        <a href="${qrUrl}" target="_blank" style="color: #60a5fa; text-decoration: none; font-size: 14px;">🎵 Play Audio</a>
      </div>
      <img src="${imageUrl}" alt="Complete Memory Frame" style="max-width: 100%; max-height: 100%;">
    </div>
  `;
  
  document.body.appendChild(modal);
  window.currentImageModal = modal;
  
  // Add keyboard support
  document.addEventListener('keydown', window.handleImageModalKeydown);
};

// Function to close image modal
window.closeImageModal = function() {
  if (window.currentImageModal) {
    window.currentImageModal.remove();
    window.currentImageModal = null;
    document.removeEventListener('keydown', window.handleImageModalKeydown);
  }
};

// Handle keyboard events for image modal
window.handleImageModalKeydown = function(e) {
  if (e.key === 'Escape') {
    window.closeImageModal();
  }
};

// Function to show image modal (from main HTML)
window.showImageModal = function() {
  const imageLink = document.getElementById('imageLink');
  const imageUrl = imageLink ? imageLink.href : null;
  
  if (!imageUrl || imageUrl === '#') {
    if (window.showToast) {
      window.showToast('No image available to display', 'warning');
    } else {
      alert('No image available to display');
    }
    return;
  }
  
  // Create modal overlay
  const modal = document.createElement('div');
  modal.className = 'image-modal';
  modal.onclick = (e) => {
    if (e.target === modal) window.closeImageModal();
  };
  
  modal.innerHTML = `
    <div class="image-modal-content">
      <button class="image-modal-close" onclick="closeImageModal()" title="Close">&times;</button>
      <img src="${imageUrl}" alt="Complete Memory Frame" style="max-width: 100%; max-height: 100%;">
    </div>
  `;
  
  document.body.appendChild(modal);
  window.currentImageModal = modal;
  
  // Add keyboard support
  document.addEventListener('keydown', window.handleImageModalKeydown);
};

// Load more memories function
window.loadMoreMemories = function() {
  const offset = window.currentWaveformsOffset || 0;
  console.log('🔍 Load more clicked, current offset:', offset);
  
  const currentUser = window.getCurrentUser ? window.getCurrentUser() : null;
  if (!currentUser) {
    console.log('🔍 No user for load more');
    return;
  }
  
  window.loadMoreWaveforms(offset);
};

// Function to load additional waveforms (defined in HTML script)
window.loadMoreWaveforms = async function(offset) {
  try {
    console.log('🔍 Loading more waveforms from offset:', offset);
    const currentUser = window.getCurrentUser();
    const response = await fetch(`get_waveforms.php?user_id=${encodeURIComponent(currentUser.uid)}&offset=${offset}&limit=5`);
    
    if (!response.ok) throw new Error('Failed to load more waveforms');
    
    const data = await response.json();
    console.log('🔍 Load more response:', data);
    
    const waveforms = Array.isArray(data) ? data : (data.waveforms || []);
    const hasMore = data.has_more || false;
    const total = data.total || 0;
    
    console.log('🔍 Additional waveforms:', waveforms.length, 'hasMore:', hasMore);
    
    if (waveforms.length > 0) {
      const waveformsContainer = document.getElementById('waveformsContainer');
      if (!waveformsContainer) return;
      
      // Generate HTML for additional waveforms
      const waveformItems = waveforms.map(waveform => {
        const title = waveform.title || waveform.original_name || 'Untitled';
        const date = new Date(waveform.created_at).toLocaleDateString();
        const time = new Date(waveform.created_at).toLocaleTimeString();
        
        return `
          <div class="waveform-item" style="border: 1px solid #e6e9f2; border-radius: 8px; padding: 12px; margin-bottom: 8px; background: #fafbfc;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div>
                <strong style="color: #0b0d12;">${title}</strong>
                <div class="muted" style="font-size: 12px; margin-top: 2px;">
                  ${waveform.original_name} • ${date} ${time}
                </div>
              </div>
              <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button onclick="showMemoryModal('${waveform.image_url}', '${title.replace(/'/g, "\\'")}', '${waveform.qr_url}')" class="secondary" style="font-size: 12px; padding: 4px 8px; border: none; cursor: pointer;">View</button>
                <a href="${waveform.qr_url}" target="_blank" class="secondary" style="font-size: 12px; padding: 4px 8px;">QR</a>
                <button onclick="showOrderOptions(${waveform.id}, '${waveform.image_url}', '${title.replace(/'/g, "\\'")}', this)" style="background: #2a4df5; border: none; color: white; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px;">Order Print</button>
                <button onclick="deleteMemory(${waveform.id}, '${title.replace(/'/g, "\\'")}', this)" class="btn-delete" style="background: #dc3545; border: none; color: white; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px;">Delete</button>
              </div>
            </div>
          </div>
        `;
      }).join('');
      
      // Remove existing load more button
      const existingLoadMore = waveformsContainer.querySelector('#loadMoreBtn');
      if (existingLoadMore) {
        existingLoadMore.remove();
      }
      
      // Append new items
      waveformsContainer.insertAdjacentHTML('beforeend', waveformItems);
      
      // Add new load more button if there are more items
      if (hasMore) {
        const loadMoreBtn = document.createElement('div');
        loadMoreBtn.id = 'loadMoreBtn';
        loadMoreBtn.style.cssText = 'text-align: center; margin-top: 16px;';
        loadMoreBtn.innerHTML = `
          <button onclick="loadMoreMemories()" style="background: #6b7280; border: none; color: white; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 500;">
            Load More (${total - offset - waveforms.length} remaining)
          </button>
        `;
        waveformsContainer.appendChild(loadMoreBtn);
      }
      
      // Update offset for next load more
      window.currentWaveformsOffset = offset + waveforms.length;
      console.log('🔍 Updated offset to:', window.currentWaveformsOffset);
    }
    
  } catch (error) {
    console.error('🔍 Error loading more waveforms:', error);
    if (window.handleError) {
      window.handleError(error, 'Load More Memories');
    } else {
      alert('Error loading more memories: ' + error.message);
    }
  }
};

// Placeholder functions for order functionality (these will be implemented in HTML)
window.showOrderOptions = function(memoryId, imageUrl, title, buttonElement) {
  console.log('showOrderOptions called:', { memoryId, imageUrl, title });
  // This function is implemented in the main HTML script
  if (window.showToast) {
    window.showToast('Order functionality will be implemented', 'info');
  } else {
    alert('Order functionality will be implemented');
  }
};

window.closeOrderModal = function() {
  if (window.currentOrderModal) {
    window.currentOrderModal.remove();
    window.currentOrderModal = null;
  }
};

console.log('🌟 Global functions loaded and available');
