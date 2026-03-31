document.addEventListener('click', async (event) => {
  if (!(event.target instanceof Element)) {
    return;
  }

  const button = event.target.closest('.js-copy-text');

  if (!(button instanceof HTMLButtonElement)) {
    return;
  }

  const text = button.dataset.copyText || '';

  if (text.trim() === '') {
    return;
  }

  const originalLabel = button.dataset.originalLabel || button.textContent.trim();
  const successLabel = button.dataset.copySuccess || 'Copiado';
  const errorLabel = button.dataset.copyError || 'Erro ao copiar';

  button.dataset.originalLabel = originalLabel;

  try {
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      await navigator.clipboard.writeText(text);
    } else {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.setAttribute('readonly', '');
      textarea.style.position = 'absolute';
      textarea.style.left = '-9999px';
      document.body.appendChild(textarea);
      textarea.select();
      const copied = document.execCommand('copy');
      document.body.removeChild(textarea);

      if (!copied) {
        throw new Error('copy_failed');
      }
    }

    button.textContent = successLabel;
    button.classList.remove('is-copy-error');
    button.classList.add('is-copied');
  } catch (error) {
    button.textContent = errorLabel;
    button.classList.remove('is-copied');
    button.classList.add('is-copy-error');
  }

  if (button.dataset.copyTimer !== undefined) {
    window.clearTimeout(Number(button.dataset.copyTimer));
  }

  const timerId = window.setTimeout(() => {
    button.textContent = originalLabel;
    button.classList.remove('is-copied', 'is-copy-error');
  }, 1800);

  button.dataset.copyTimer = String(timerId);
});

document.addEventListener('click', (event) => {
  if (!(event.target instanceof Element)) {
    return;
  }

  const button = event.target.closest('.js-fill-existing-token');

  if (!(button instanceof HTMLButtonElement)) {
    return;
  }

  const form = button.closest('form');
  const input = form instanceof HTMLFormElement ? form.querySelector('.js-existing-token-input') : null;

  if (!(input instanceof HTMLInputElement)) {
    return;
  }

  const familyName = button.dataset.familyName || 'esta família';
  const typedValue = window.prompt(
    `Cole o link atual ou só o token de ${familyName}. Isso não troca o QR code existente.`,
    ''
  );

  if (typedValue === null || typedValue.trim() === '') {
    return;
  }

  input.value = typedValue.trim();
  form.submit();
});

function manualRoleIsFeminine(roleTitle) {
  const normalizedRole = roleTitle.trim().toLowerCase();

  if (normalizedRole === '') {
    return false;
  }

  return ['madrinha', 'dama', 'florista'].some((needle) => normalizedRole.includes(needle))
    || normalizedRole.endsWith('a');
}

function manualPrimaryName(name) {
  return name.trim().split(/\s+/)[0] || '';
}

function manualRoleDefaultText(roleTitle, guestName) {
  const normalizedRole = roleTitle.trim().toLowerCase();
  const firstName = manualPrimaryName(guestName);

  if (normalizedRole.includes('dama') || normalizedRole.includes('madrinha')) {
    return firstName !== ''
      ? `${firstName} segue a paleta esmeralda para o vestido.`
      : 'Siga a paleta esmeralda para o vestido.';
  }

  if (normalizedRole.includes('pajem')) {
    return firstName !== ''
      ? `${firstName} usa traje em tons escuros com detalhe dourado.`
      : 'Use traje em tons escuros com detalhe dourado.';
  }

  if (normalizedRole.includes('padrinho')) {
    return firstName !== ''
      ? `${firstName} usa terno preto com gravata verde esmeralda.`
      : 'Use terno preto com gravata verde esmeralda.';
  }

  if (manualRoleIsFeminine(roleTitle)) {
    return firstName !== ''
      ? `${firstName} segue a paleta esmeralda para o look do grande dia.`
      : 'Siga a paleta esmeralda para o look do grande dia.';
  }

  return firstName !== ''
    ? `${firstName} usa traje social em tons escuros com detalhe verde esmeralda.`
    : 'Use traje social em tons escuros com detalhe verde esmeralda.';
}

document.addEventListener('DOMContentLoaded', () => {
  const roleTitleInput = document.querySelector('[data-manual-role-title-input]');
  const roleTextInput = document.querySelector('[data-manual-role-text-input]');
  const guestNameInput = document.querySelector('#nome');
  const guestSelectInput = document.querySelector('#guest_id');
  const manualInviteInput = document.querySelector('#manual_invite_id');

  if (
    ((roleTitleInput instanceof HTMLInputElement) || (roleTitleInput instanceof HTMLSelectElement))
    && roleTextInput instanceof HTMLTextAreaElement
  ) {
    const syncManualRoleText = () => {
      const roleTitle = roleTitleInput.value.trim();
      let guestName = guestNameInput instanceof HTMLInputElement ? guestNameInput.value.trim() : '';

      if (guestName === '' && guestSelectInput instanceof HTMLSelectElement) {
        const selectedOption = guestSelectInput.selectedOptions[0];
        const optionLabel = selectedOption instanceof HTMLOptionElement ? selectedOption.text.trim() : '';
        guestName = optionLabel.split(' - ')[0] || '';
      }

      const inviteSelected = manualInviteInput instanceof HTMLSelectElement
        ? manualInviteInput.value.trim() !== ''
        : true;
      const suggestion = inviteSelected && roleTitle !== ''
        ? manualRoleDefaultText(roleTitle, guestName)
        : '';
      const previousAutoValue = roleTextInput.dataset.manualAutoValue || '';
      const currentValue = roleTextInput.value.trim();
      const canReplace = currentValue === '' || currentValue === previousAutoValue;

      if (suggestion !== '' && canReplace) {
        roleTextInput.value = suggestion;
        roleTextInput.dataset.manualAutoValue = suggestion;
        return;
      }

      if (suggestion !== '' && currentValue === suggestion) {
        roleTextInput.dataset.manualAutoValue = suggestion;
        return;
      }

      if (suggestion === '' && canReplace) {
        roleTextInput.value = '';
        roleTextInput.dataset.manualAutoValue = '';
      }
    };

    roleTitleInput.addEventListener('input', syncManualRoleText);

    if (roleTitleInput instanceof HTMLSelectElement) {
      roleTitleInput.addEventListener('change', syncManualRoleText);
    }

    roleTextInput.addEventListener('input', () => {
      const previousAutoValue = roleTextInput.dataset.manualAutoValue || '';

      if (previousAutoValue !== '' && roleTextInput.value.trim() !== previousAutoValue) {
        roleTextInput.dataset.manualAutoValue = '';
      }
    });

    if (guestNameInput instanceof HTMLInputElement) {
      guestNameInput.addEventListener('input', syncManualRoleText);
    }

    if (guestSelectInput instanceof HTMLSelectElement) {
      guestSelectInput.addEventListener('change', syncManualRoleText);
    }

    if (manualInviteInput instanceof HTMLSelectElement) {
      manualInviteInput.addEventListener('change', syncManualRoleText);
    }

    syncManualRoleText();
  }

  const previewFrame = document.querySelector('[data-manual-preview-frame]');
  const previewSource = document.querySelector('[data-manual-preview-srcdoc]');

  if (!(previewFrame instanceof HTMLIFrameElement) || !(previewSource instanceof HTMLTextAreaElement)) {
    return;
  }

  const previewRecommendations = (() => {
    try {
      return JSON.parse(previewFrame.dataset.previewRecommendations || '[]');
    } catch (error) {
      return [];
    }
  })();

  const previewBindings = {
    question: '[data-preview-question]',
    intro_title: '[data-preview-intro-title]',
    intro_line_1: '[data-preview-intro-line-1]',
    intro_line_2: '[data-preview-intro-line-2]',
    calendar_title: '[data-preview-calendar-title]',
    calendar_month: '[data-preview-calendar-month]',
    day_title: '[data-preview-day-title]',
    day_line_1: '[data-preview-day-line-1]',
    day_line_2: '[data-preview-day-line-2]',
    day_line_3: '[data-preview-day-line-3]',
    thanks: '[data-preview-thanks]',
  };

  const previewSwatchesForRole = (roleTitle) => {
    const normalizedRole = roleTitle.trim().toLowerCase();

    if (normalizedRole.includes('pajem') || normalizedRole.includes('padrinho')) {
      return ['manual-swatch--black', 'manual-swatch--emerald-1'];
    }

    return ['manual-swatch--emerald-1', 'manual-swatch--emerald-2', 'manual-swatch--emerald-3'];
  };

  const splitCoverLines = (name) => {
    const trimmedName = name.trim();

    if (trimmedName === '') {
      return ['Convite'];
    }

    const parts = trimmedName.split(/\s+/);

    if (parts.length <= 1) {
      return [trimmedName];
    }

    return [parts.shift() || trimmedName, parts.join(' ')];
  };

  const getPreviewDocument = () => (
    previewFrame.contentDocument instanceof Document ? previewFrame.contentDocument : null
  );

  const refreshPreviewSuggestions = () => {
    const previewDocument = getPreviewDocument();

    if (!(previewDocument instanceof Document)) {
      return;
    }

    const cardsContainer = previewDocument.querySelector('[data-preview-recommendations]');

    if (!(cardsContainer instanceof HTMLElement)) {
      return;
    }

    const titles = Array.from(
      previewDocument.querySelectorAll('[data-preview-role-title]')
    )
      .map((node) => node.textContent.trim())
      .filter((title) => title !== '');

    if (titles.length === 0) {
      return;
    }

    const hasFeminine = titles.some((title) => manualRoleIsFeminine(title));
    const hasMasculine = titles.some((title) => !manualRoleIsFeminine(title));
    const labels = [];

    if (hasFeminine) {
      labels.push('Make e cabelo', 'Vestido das madrinhas');
    }

    if (hasMasculine) {
      labels.push('Sugestão terno padrinhos');
    }

    const activeRecommendations = previewRecommendations.filter((recommendation) => (
      labels.includes(recommendation.label)
    ));

    cardsContainer.replaceChildren(
      ...activeRecommendations.map((recommendation) => {
        const card = previewDocument.createElement('article');
        card.className = 'manual-recommendation-card';

        const kicker = previewDocument.createElement('p');
        kicker.className = 'manual-recommendation-kicker';
        kicker.textContent = recommendation.label;

        const title = previewDocument.createElement('h3');
        title.textContent = recommendation.name;

        const links = previewDocument.createElement('div');
        links.className = 'manual-recommendation-links';

        const phoneLink = previewDocument.createElement('a');
        phoneLink.className = 'manual-recommendation-link';
        phoneLink.href = recommendation.phone_href;
        phoneLink.textContent = recommendation.phone;

        const instagramLink = previewDocument.createElement('a');
        instagramLink.className = 'manual-recommendation-link';
        instagramLink.href = recommendation.url;
        instagramLink.target = '_blank';
        instagramLink.rel = 'noreferrer';
        instagramLink.textContent = 'Instagram';

        links.append(phoneLink, instagramLink);
        card.append(kicker, title, links);

        if ((recommendation.note || '').trim() !== '') {
          const note = previewDocument.createElement('p');
          note.className = 'manual-recommendation-note';
          note.textContent = recommendation.note;
          card.append(note);
        }

        return card;
      })
    );
  };

  const refreshPreviewCover = () => {
    const previewDocument = getPreviewDocument();

    if (!(previewDocument instanceof Document)) {
      return;
    }

    const titleShell = previewDocument.querySelector('[data-preview-cover-title]');
    const lineOne = previewDocument.querySelector('[data-preview-cover-line="0"]');
    const lineTwo = previewDocument.querySelector('[data-preview-cover-line="1"]');
    const ampersand = previewDocument.querySelector('[data-preview-cover-ampersand]');

    if (
      !(titleShell instanceof HTMLElement)
      || !(lineOne instanceof HTMLElement)
      || !(lineTwo instanceof HTMLElement)
      || !(ampersand instanceof HTMLElement)
    ) {
      return;
    }

    const names = Array.from(previewDocument.querySelectorAll('[data-preview-role-name]'))
      .map((node) => node.textContent.trim())
      .filter((name) => name !== '');

    if (names.length > 1) {
      titleShell.classList.remove('manual-cover-title--single');
      lineOne.textContent = names[0];
      lineTwo.textContent = names[1];
      lineTwo.hidden = false;
      ampersand.hidden = false;
      return;
    }

    const [firstLine, secondLine] = splitCoverLines(names[0] || 'Convite');
    titleShell.classList.add('manual-cover-title--single');
    lineOne.textContent = firstLine;
    lineTwo.textContent = secondLine || '';
    lineTwo.hidden = !secondLine;
    ampersand.hidden = true;
  };

  const syncPreviewTextBindings = () => {
    const previewDocument = getPreviewDocument();

    if (!(previewDocument instanceof Document)) {
      return;
    }

    Object.entries(previewBindings).forEach(([source, selector]) => {
      const input = document.querySelector(`[data-preview-source="${source}"]`);
      const target = previewDocument.querySelector(selector);

      if (
        !((input instanceof HTMLInputElement) || (input instanceof HTMLTextAreaElement))
        || !(target instanceof HTMLElement)
      ) {
        return;
      }

      const sync = () => {
        const fallback = target.dataset.previewFallback || '';
        target.textContent = input.value.trim() || fallback;
      };

      input.addEventListener('input', sync);
      input.addEventListener('change', sync);
      sync();
    });
  };

  const syncPreviewRoleBindings = () => {
    const previewDocument = getPreviewDocument();

    if (!(previewDocument instanceof Document)) {
      return;
    }

    document.querySelectorAll('[data-preview-role-title-source]').forEach((sourceNode) => {
      if (!(sourceNode instanceof HTMLSelectElement)) {
        return;
      }

      const index = sourceNode.dataset.previewRoleTitleSource || '';
      const roleCard = previewDocument.querySelector(`[data-preview-role-index="${index}"]`);
      const target = roleCard instanceof HTMLElement
        ? roleCard.querySelector('[data-preview-role-title]')
        : null;
      const swatchesTarget = roleCard instanceof HTMLElement
        ? roleCard.querySelector('[data-preview-role-swatches]')
        : null;
      const textTarget = roleCard instanceof HTMLElement
        ? roleCard.querySelector('[data-preview-role-text]')
        : null;
      const nameTarget = roleCard instanceof HTMLElement
        ? roleCard.querySelector('[data-preview-role-name]')
        : null;
      const textSourceNode = document.querySelector(`[data-preview-role-text-source="${index}"]`);

      if (!(target instanceof HTMLElement)) {
        return;
      }

      const sync = () => {
        const roleTitle = sourceNode.value.trim() || 'Convidado';
        target.textContent = roleTitle;

        if (swatchesTarget instanceof HTMLElement) {
          swatchesTarget.replaceChildren(
            ...previewSwatchesForRole(roleTitle).map((className) => {
              const swatch = previewDocument.createElement('span');
              swatch.className = `manual-swatch ${className}`;
              return swatch;
            })
          );
        }

        if (textSourceNode instanceof HTMLTextAreaElement && textTarget instanceof HTMLElement) {
          const guestName = nameTarget instanceof HTMLElement ? nameTarget.textContent.trim() : '';
          const firstName = guestName.split(/\s+/)[0] || '';
          textTarget.textContent = textSourceNode.value.trim() || manualRoleDefaultText(roleTitle, firstName);
        }

        refreshPreviewSuggestions();
      };

      sourceNode.addEventListener('input', sync);
      sourceNode.addEventListener('change', sync);
      sync();
    });

    document.querySelectorAll('[data-preview-role-text-source]').forEach((sourceNode) => {
      if (!(sourceNode instanceof HTMLTextAreaElement)) {
        return;
      }

      const index = sourceNode.dataset.previewRoleTextSource || '';
      const roleCard = previewDocument.querySelector(`[data-preview-role-index="${index}"]`);
      const target = roleCard instanceof HTMLElement
        ? roleCard.querySelector('[data-preview-role-text]')
        : null;
      const titleTarget = roleCard instanceof HTMLElement
        ? roleCard.querySelector('[data-preview-role-title]')
        : null;
      const nameTarget = roleCard instanceof HTMLElement
        ? roleCard.querySelector('[data-preview-role-name]')
        : null;

      if (!(target instanceof HTMLElement)) {
        return;
      }

      const sync = () => {
        const roleTitle = titleTarget instanceof HTMLElement ? titleTarget.textContent.trim() : 'Convidado';
        const guestName = nameTarget instanceof HTMLElement ? nameTarget.textContent.trim() : '';
        const firstName = guestName.split(/\s+/)[0] || '';
        target.textContent = sourceNode.value.trim() || manualRoleDefaultText(roleTitle, firstName);
      };

      sourceNode.addEventListener('input', sync);
      sourceNode.addEventListener('change', sync);
      sync();
    });
  };

  const initializePreviewFrame = () => {
    syncPreviewTextBindings();
    syncPreviewRoleBindings();
    refreshPreviewCover();
    refreshPreviewSuggestions();
  };

  previewFrame.addEventListener('load', initializePreviewFrame, { once: true });
  previewFrame.srcdoc = previewSource.value;
});
