(function() {
	'use strict'

	const loginPath = window.location.pathname.replace(/\/$/, '')
	if (!loginPath.endsWith('/login')) {
		return
	}

	const removeOidcLoginButton = function() {
		const container = document.getElementById('alternative-logins')
		if (!container) {
			return false
		}

		const oidcLinks = container.querySelectorAll('a[href*="/apps/user_oidc/"]')
		for (const link of oidcLinks) {
			link.remove()
		}

		if (!container.querySelector('a, button')) {
			container.hidden = true
		}

		return oidcLinks.length > 0
	}

	const start = function() {
		if (removeOidcLoginButton()) {
			return
		}

		const observer = new MutationObserver(function() {
			if (removeOidcLoginButton()) {
				observer.disconnect()
			}
		})
		observer.observe(document.body, { childList: true, subtree: true })
		window.setTimeout(function() {
			observer.disconnect()
		}, 10000)
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, { once: true })
	} else {
		start()
	}
})()
