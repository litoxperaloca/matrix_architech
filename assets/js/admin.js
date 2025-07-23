// assets/js/admin.js
const { createApp, ref, nextTick, onMounted } = Vue;

createApp({
  setup() {
    const view = ref('action_selector'); // 'action_selector', 'content_form', 'chat'
    const currentAction = ref('');
    const currentActionLabel = ref('');
    
    const contentPrompt = ref('');
    const imagePrompt = ref('');
    const keywords = ref('');
    const tone = ref('Profesional');

    const messages = ref([]);
    const chatInput = ref('');
    const isLoading = ref(false);
    const chatContainer = ref(null);

    const selectAction = (action, label) => {
        currentAction.value = action;
        currentActionLabel.value = label;
        
        let initialText = `Modo seleccionado: <strong>${label}</strong>. Soy El Oráculo de la Matriz, listo para construir. ¿Qué tienes en mente?`;
        
        if (action === 'evaluate_content') {
            if (ma_config.evaluate_plugin) {
                const { slug, files } = ma_config.evaluate_plugin;
                let files_context = `He cargado el código del plugin <strong>${slug}</strong>. Aquí están sus archivos:\n\n`;
                for(const filename in files) {
                    files_context += `--- ${filename} ---\n${files[filename]}\n\n`;
                }
                 messages.value = [
                    { id: Date.now(), sender: 'ai', text: "Modo de Evaluación activado. He cargado el código del plugin. Por favor, describe las mejoras que te gustaría hacer o pide una evaluación general." },
                    { id: Date.now() + 1, sender: 'system', text: files_context }
                 ];

            } else {
                 initialText = `Modo seleccionado: <strong>${label}</strong>. Por favor, navega al dashboard 'Mis Plugins' y haz clic en 'Evaluar y Mejorar' en un plugin existente para cargarlo aquí.`;
                 messages.value = [{ id: Date.now(), text: initialText, sender: 'ai' }];
            }
        } else {
             messages.value = [{ 
                id: Date.now(), 
                text: initialText, 
                sender: 'ai' 
            }];
        }
        
        view.value = 'chat';
    };

    const startChatFromForm = () => {
        let initialPrompt = `**Acción:** ${currentActionLabel.value}\n**Prompt Principal:** ${contentPrompt.value}\n**Prompt para Imagen:** ${imagePrompt.value}\n**Palabras Clave:** ${keywords.value}\n**Tono:** ${tone.value}`;
        messages.value = [
            { id: Date.now(), text: `Iniciando creación de contenido...`, sender: 'system' },
            { id: Date.now() + 1, text: initialPrompt, sender: 'user' }
        ];
        view.value = 'chat';
        sendMessage(true);
    };

    const scrollToBottom = () => {
      nextTick(() => {
        if (chatContainer.value) {
          chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
      });
    };

    onMounted(scrollToBottom);

    const sendMessage = async (fromContentForm = false) => {
      const textToSend = fromContentForm ? messages.value[messages.value.length - 1].text : chatInput.value.trim();
      if (!textToSend) return;

      if (!fromContentForm) {
        messages.value.push({ id: Date.now(), text: textToSend, sender: 'user' });
      }
      
      chatInput.value = '';
      isLoading.value = true;
      scrollToBottom();
      
      const aiMessageId = Date.now() + 1;
      messages.value.push({ id: aiMessageId, text: '', sender: 'ai' });
      scrollToBottom();

      try {
        const payload = {
            action: currentAction.value,
            history: messages.value.map(m => ({text: String(m.text || ''), sender: m.sender})),
            image_prompt: imagePrompt.value,
            keywords: keywords.value,
            tone: tone.value
        };

        const response = await fetch(ma_config.rest_url + 'chat', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': ma_config.nonce,
          },
          body: JSON.stringify(payload),
        });

        const reader = response.body.getReader();
        const decoder = new TextDecoder();

        while (true) {
          const { value, done } = await reader.read();
          if (done) break;
          
          const chunk = decoder.decode(value, { stream: true });
          const lines = chunk.split('\n\n');

          lines.forEach(line => {
            if (line.startsWith('data: ')) {
              const dataStr = line.substring(6);
              if (dataStr.trim() === '{"status":"done"}') return;
              try {
                const data = JSON.parse(dataStr);
                const aiMessage = messages.value.find(m => m.id === aiMessageId);
                if (aiMessage && data.chunk) {
                  aiMessage.text += atob(data.chunk);
                  scrollToBottom();
                }
              } catch (e) {
                console.error('Error al parsear el chunk JSON:', dataStr, e);
              }
            }
          });
        }
      } catch (error) {
        const aiMessage = messages.value.find(m => m.id === aiMessageId);
        if (aiMessage) {
            aiMessage.text = `Hubo un problema: ${error.message}`;
            aiMessage.isError = true;
        }
      } finally {
        isLoading.value = false;
        scrollToBottom();
      }
    };

    return { 
        view, messages, contentPrompt, imagePrompt, keywords, tone, isLoading, 
        chatContainer, currentActionLabel, chatInput,
        selectAction, startChatFromForm, sendMessage 
    };
  },
  template: `
    <div v-if="view === 'action_selector'" class="action-selector">
        <h2>¿Qué quieres construir hoy, Neo?</h2>
        <div class="action-buttons">
            <button @click="selectAction('create_plugin', 'Crear Plugin')">
                <span class="dashicons dashicons-admin-plugins"></span>
                Crear un Plugin
            </button>
            <button @click="selectAction('create_page', 'Crear Página')">
                <span class="dashicons dashicons-admin-page"></span>
                Crear una Página
            </button>
            <button @click="selectAction('create_post', 'Crear Entrada')">
                <span class="dashicons dashicons-admin-post"></span>
                Crear una Entrada
            </button>
            <button @click="selectAction('generate_image', 'Generar Imagen')">
                <span class="dashicons dashicons-format-image"></span>
                Generar Imagen
            </button>
            <button @click="selectAction('evaluate_content', 'Evaluar Contenido')">
                <span class="dashicons dashicons-search"></span>
                Evaluar y Mejorar
            </button>
        </div>
    </div>

    <div v-if="view === 'content_form'" class="content-form-wrapper">
        <h2>Crear {{ currentActionLabel }}</h2>
        <div class="form-grid">
            <div class="form-field full-width">
                <label for="content-prompt">Prompt Principal</label>
                <textarea id="content-prompt" v-model="contentPrompt" rows="5" placeholder="Ej: Un artículo sobre los beneficios de la IA para el desarrollo en WordPress..."></textarea>
            </div>
            <div class="form-field">
                <label for="image-prompt">Prompt para Imagen Destacada</label>
                <input type="text" id="image-prompt" v-model="imagePrompt" placeholder="Ej: Un cerebro hecho de circuitos brillantes y código binario">
            </div>
            <div class="form-field">
                <label for="keywords">Palabras Clave (separadas por coma)</label>
                <input type="text" id="keywords" v-model="keywords" placeholder="Ej: IA, WordPress, desarrollo, optimización">
            </div>
            <div class="form-field">
                <label for="tone">Tono del Contenido</label>
                <select id="tone" v-model="tone">
                    <option>Profesional</option>
                    <option>Casual</option>
                    <option>Técnico</option>
                    <option>Humorístico</option>
                    <option>Inspirador</option>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button @click="startChatFromForm" class="button button-primary button-hero">Generar Contenido</button>
        </div>
    </div>

    <div v-if="view === 'chat'" class="matrix-architech-chat-wrapper">
      <div class="chat-container" ref="chatContainer">
        <div v-for="message in messages" :key="message.id" :class="['chat-message', 'message-' + message.sender, { 'message-error': message.isError }]">
          <div class="chat-bubble">
            <p v-html="String(message.text || '').replace(/\\n/g, '<br>')"></p>
          </div>
        </div>
        <div v-if="isLoading && (!messages.length || messages[messages.length - 1].sender !== 'ai')" class="chat-message message-ai">
          <div class="chat-bubble">
            <div class="typing-indicator"><div></div><div></div><div></div></div>
          </div>
        </div>
      </div>
      <div class="chat-input-area">
        <textarea v-model="chatInput" @keydown.enter.prevent="sendMessage()" placeholder="Describe lo que necesitas..." :disabled="isLoading" rows="1"></textarea>
        <button @click="sendMessage()" :disabled="isLoading || !chatInput" class="send-btn">
          <span class="dashicons dashicons-arrow-right-alt"></span>
        </button>
      </div>
    </div>
  `
}).mount('#matrix-architech-app');
