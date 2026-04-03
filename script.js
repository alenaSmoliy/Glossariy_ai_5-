(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const glossaryId = document.getElementById('glossary-id')?.value;
        const courseId = document.getElementById('course-id')?.value;
        const sesskey = document.getElementById('sesskey')?.value;
        
        if (!glossaryId || !courseId) return;
        
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `glossary-ai-notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }
        
        async function apiRequest(action, data) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('course_id', courseId);
            formData.append('sesskey', sesskey);
            
            for (const [key, value] of Object.entries(data)) {
                formData.append(key, value);
            }
            
            try {
                const response = await fetch('/local/glossary_ai/ajax_handler.php', {
                    method: 'POST',
                    body: formData
                });
                return await response.json();
            } catch (error) {
                console.error('API Error:', error);
                return { success: false, error: error.message };
            }
        }
        
        async function addToGlossary(btn) {
            const row = btn.closest('tr');
            const index = row.dataset.index;
            const termInput = row.querySelector('.term-input');
            const defInput = row.querySelector('.definition-input');
            const term = termInput.value.trim();
            const definition = defInput.value.trim();
            
            if (!term || !definition) {
                showNotification('Заполните и термин, и определение', 'error');
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '<span class="glossary-ai-spinner"></span>';
            
            const result = await apiRequest('add_to_glossary', {
                glossary_id: glossaryId,
                term: term,
                definition: definition
            });
            
            if (result.success) {
                row.dataset.added = 'true';
                row.classList.add('added');
                btn.innerHTML = '✓';
                btn.disabled = true;
                showNotification('✅ Термин добавлен в глоссарий');
            } else if (result.error === 'duplicate') {
                showNotification('⚠️ Такой термин уже существует', 'error');
                btn.innerHTML = '➕';
                btn.disabled = false;
            } else {
                showNotification('❌ Ошибка: ' + (result.error || 'неизвестная ошибка'), 'error');
                btn.innerHTML = '➕';
                btn.disabled = false;
            }
        }
        
        async function addAllTerms() {
            const rows = document.querySelectorAll('#terms-tbody tr');
            const terms = [];
            
            rows.forEach(row => {
                if (row.dataset.added === 'true') return;
                
                const termInput = row.querySelector('.term-input');
                const defInput = row.querySelector('.definition-input');
                const term = termInput.value.trim();
                const definition = defInput.value.trim();
                
                if (term && definition) {
                    terms.push({ term, definition });
                }
            });
            
            if (terms.length === 0) {
                showNotification('Нет терминов для добавления', 'error');
                return;
            }
            
            const addAllBtn = document.getElementById('add-all-terms');
            const originalText = addAllBtn.innerHTML;
            addAllBtn.innerHTML = '<span class="glossary-ai-spinner"></span> Добавление...';
            addAllBtn.disabled = true;
            
            const result = await apiRequest('add_all_terms', {
                glossary_id: glossaryId,
                terms: JSON.stringify(terms)
            });
            
            addAllBtn.innerHTML = originalText;
            addAllBtn.disabled = false;
            
            if (result.success) {
                showNotification(`✅ Добавлено ${result.added} терминов. Дубликатов: ${result.duplicates || 0}`);
                
                // Отмечаем добавленные строки
                rows.forEach(row => {
                    if (row.dataset.added !== 'true') {
                        row.dataset.added = 'true';
                        row.classList.add('added');
                        const addBtn = row.querySelector('.add-term-btn');
                        if (addBtn) {
                            addBtn.innerHTML = '✓';
                            addBtn.disabled = true;
                        }
                    }
                });
            } else {
                showNotification('❌ Ошибка: ' + (result.error || 'неизвестная ошибка'), 'error');
            }
        }
        
        function addNewTermRow() {
            const tbody = document.getElementById('terms-tbody');
            const index = tbody.children.length;
            const newRow = document.createElement('tr');
            newRow.dataset.index = index;
            newRow.dataset.added = 'false';
            newRow.innerHTML = `
                <td><textarea class="term-input" rows="2" placeholder="Введите термин"></textarea></td>
                <td><textarea class="definition-input" rows="2" placeholder="Введите определение"></textarea></td>
                <td class="glossary-ai-action-buttons">
                    <button class="glossary-ai-btn glossary-ai-btn-sm save-new-term-btn">💾 Сохранить</button>
                    <button class="glossary-ai-btn glossary-ai-btn-sm glossary-ai-btn-danger cancel-new-term-btn">✖️</button>
                </td>
            `;
            tbody.appendChild(newRow);
            
            const saveBtn = newRow.querySelector('.save-new-term-btn');
            const cancelBtn = newRow.querySelector('.cancel-new-term-btn');
            
            saveBtn.onclick = async function() {
                const termInput = newRow.querySelector('.term-input');
                const defInput = newRow.querySelector('.definition-input');
                const term = termInput.value.trim();
                const definition = defInput.value.trim();
                
                if (!term || !definition) {
                    showNotification('Заполните и термин, и определение', 'error');
                    return;
                }
                
                const result = await apiRequest('add_term', { term, definition });
                
                if (result.success) {
                    newRow.dataset.index = result.index;
                    newRow.innerHTML = `
                        <td><textarea class="term-input" rows="2">${escapeHtml(term)}</textarea></td>
                        <td><textarea class="definition-input" rows="2">${escapeHtml(definition)}</textarea></td>
                        <td class="glossary-ai-action-buttons">
                            <button class="glossary-ai-btn glossary-ai-btn-sm update-term-btn" data-index="${result.index}">✏️</button>
                            <button class="glossary-ai-btn glossary-ai-btn-sm glossary-ai-btn-danger delete-term-btn" data-index="${result.index}">🗑️</button>
                            <button class="glossary-ai-btn glossary-ai-btn-sm glossary-ai-btn-success add-term-btn" data-index="${result.index}">➕</button>
                        </td>
                    `;
                    attachRowHandlers(newRow);
                    showNotification('✅ Термин добавлен');
                }
            };
            
            cancelBtn.onclick = () => newRow.remove();
        }
        
        async function updateTerm(btn) {
            const row = btn.closest('tr');
            const index = row.dataset.index;
            const termInput = row.querySelector('.term-input');
            const defInput = row.querySelector('.definition-input');
            
            const result = await apiRequest('update_term', {
                index: index,
                term: termInput.value,
                definition: defInput.value
            });
            
            if (result.success) {
                showNotification('✅ Термин обновлён');
            } else {
                showNotification('❌ Ошибка при обновлении', 'error');
            }
        }
        
        async function deleteTerm(btn) {
            if (!confirm('Удалить этот термин?')) return;
            
            const row = btn.closest('tr');
            const index = row.dataset.index;
            
            const result = await apiRequest('delete_term', { index: index });
            
            if (result.success) {
                row.remove();
                showNotification('🗑️ Термин удалён');
                // Обновляем индексы
                document.querySelectorAll('#terms-tbody tr').forEach((row, newIndex) => {
                    row.dataset.index = newIndex;
                });
            } else {
                showNotification('❌ Ошибка при удалении', 'error');
            }
        }
        
        function attachRowHandlers(row) {
            const updateBtn = row.querySelector('.update-term-btn');
            const deleteBtn = row.querySelector('.delete-term-btn');
            const addBtn = row.querySelector('.add-term-btn');
            
            if (updateBtn) updateBtn.onclick = () => updateTerm(updateBtn);
            if (deleteBtn) deleteBtn.onclick = () => deleteTerm(deleteBtn);
            if (addBtn) addBtn.onclick = () => addToGlossary(addBtn);
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Инициализация
        document.querySelectorAll('#terms-tbody tr').forEach(row => attachRowHandlers(row));
        
        document.getElementById('add-all-terms')?.addEventListener('click', addAllTerms);
        document.getElementById('add-new-term')?.addEventListener('click', addNewTermRow);
    });
})();
