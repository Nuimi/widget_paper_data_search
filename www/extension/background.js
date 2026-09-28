importScripts('client.js');

const ready = chrome.storage.local.setAccessLevel({ accessLevel: 'TRUSTED_CONTEXTS' });
const client = createQFinderClient({
    storage: chrome.storage.local,
    onSession: async expiresAt => {
        await chrome.alarms.clear('token-expiry');
        if (expiresAt) {
            await chrome.alarms.create('token-expiry', { when: expiresAt * 1000 });
        } else {
            await chrome.action.setBadgeText({ text: '' });
            for (const tab of await chrome.tabs.query({})) {
                try { await chrome.action.setBadgeText({ text: '', tabId: tab.id }); } catch { /* Tab closed during cleanup. */ }
            }
        }
    },
});
chrome.alarms.onAlarm.addListener(async alarm => {
    if (alarm.name === 'token-expiry') { await ready; await client.status(); }
});
chrome.runtime.onStartup.addListener(async () => { await ready; await client.status(); });

chrome.runtime.onInstalled.addListener(() => {
    chrome.contextMenus.create({
        id: "qQuartileLookup",
        title: "Find out quartile (Q1–Q4)",
        contexts: ["selection"],
    });
});

async function openPopupIfSupported(tab) {
    if (!chrome.action || typeof chrome.action.openPopup !== "function") {
        return;
    }

    try {
        const options = tab?.windowId ? { windowId: tab.windowId } : undefined;
        await chrome.action.openPopup(options);
    } catch (error) {
        console.log("Popup auto-open is not available", error);
    }
}

async function clearBadge(tabId) {
    if (!tabId) {
        return;
    }

    await chrome.action.setBadgeText({
        text: "",
        tabId,
    });
}

async function runLookup(query, tab) {
    const result = await client.lookup(query);
    if (result.cancelled) return result;
    const quartile = result.ok ? extractQuartile(result.data) : null;
    if (tab?.id) {
        await chrome.action.setBadgeText({ text: quartile ? quartile.toUpperCase() : '', tabId: tab.id });
    }
    return result;
}

chrome.contextMenus.onClicked.addListener(async (info, tab) => {
    if (info.menuItemId !== 'qQuartileLookup') return;
    await ready;
    try {
        const result = await runLookup(info.selectionText, tab);
        if (result.error && !result.authRequired && !result.cancelled) await chrome.storage.local.set({ lastResult: { error: result.error } });
    } catch (error) {
        console.warn('Q-Finder extension operation failed', { type: error?.name || 'Error' });
        await clearBadge(tab?.id);
        await chrome.storage.local.set({ lastResult: { error: 'The extension could not complete the operation. Reload the extension and retry.' } });
    }
    await openPopupIfSupported(tab);
});

chrome.runtime.onMessage.addListener((message, sender, respond) => {
    // Only extension pages may initiate authenticated operations, never content scripts.
    if (sender.id !== chrome.runtime.id || sender.tab || !sender.url?.startsWith(chrome.runtime.getURL(''))) return;
    if (!['AUTH_STATUS', 'AUTH_LOGIN', 'AUTH_LOGOUT', 'LOOKUP'].includes(message?.type)) return;
    (async () => {
        await ready;
        switch (message.type) {
            case 'AUTH_STATUS': return client.status();
            case 'AUTH_LOGIN': return client.login(message.login, message.password);
            case 'AUTH_LOGOUT': return client.logout();
            case 'LOOKUP': {
                const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
                return runLookup(message.query, tab);
            }
        }
    })().then(respond, error => {
        console.warn('Q-Finder extension operation failed', { type: error?.name || 'Error' });
        respond({ error: 'The extension could not complete the operation. Reload the extension and retry.' });
    });
    return true;
});

function extractQuartile(data) {
    const qData = data?.qData ?? data?.raw?.qData ?? data?.metrics?.qData;

    return (
        data?.quartile ||
        qData?.ranks?.jif?.[0]?.quartile ||
        qData?.ranks?.jci?.[0]?.quartile ||
        qData?.ranks?.articleInfluence?.[0]?.quartile ||
        null
    );
}
