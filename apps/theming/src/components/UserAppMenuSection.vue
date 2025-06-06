<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="t('theming', 'Navigation bar settings')">
		<p>
			{{ t('theming', 'You can configure the app order used for the navigation bar. The first entry will be the default app, opened after login or when clicking on the logo.') }}
		</p>
		<NcNoteCard v-if="enforcedDefaultApp" :id="elementIdEnforcedDefaultApp" type="info">
			{{ t('theming', 'The default app can not be changed because it was configured by the administrator.') }}
		</NcNoteCard>
		<NcNoteCard v-if="hasAppOrderChanged" :id="elementIdAppOrderChanged" type="info">
			{{ t('theming', 'The app order was changed, to see it in action you have to reload the page.') }}
		</NcNoteCard>

		<AppOrderSelector class="user-app-menu-order"
			:aria-details="ariaDetailsAppOrder"
			:value="appOrder"
			@update:value="updateAppOrder" />

		<NcButton data-test-id="btn-apporder-reset"
			:disabled="!hasCustomAppOrder"
			type="tertiary"
			@click="resetAppOrder">
			<template #icon>
				<IconUndo :size="20" />
			</template>
			{{ t('theming', 'Reset default app order') }}
		</NcButton>
	</NcSettingsSection>
</template>

<script lang="ts">
import type { IApp } from './AppOrderSelector.vue'
import type { INavigationEntry } from '../../../../core/src/types/navigation.d.ts'

import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, defineComponent, ref } from 'vue'

import axios from '@nextcloud/axios'
import AppOrderSelector from './AppOrderSelector.vue'
import IconUndo from 'vue-material-design-icons/Undo.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

/** The app order user setting */
type IAppOrder = Record<string, { order: number, app?: string }>

/** OCS responses */
interface IOCSResponse<T> {
	ocs: {
		meta: unknown
		data: T
	}
}

export default defineComponent({
	name: 'UserAppMenuSection',
	components: {
		AppOrderSelector,
		IconUndo,
		NcButton,
		NcNoteCard,
		NcSettingsSection,
	},
	setup() {
		const {
			/** The app order currently defined by the user */
			userAppOrder,
			/** The enforced default app set by the administrator (if any) */
			enforcedDefaultApp,
		} = loadState<{ userAppOrder: IAppOrder, enforcedDefaultApp: string }>('theming', 'navigationBar')

		/**
		 * Array of all available apps, it is set by a core controller for the app menu, so it is always available
		 */
		 const initialAppOrder = loadState<INavigationEntry[]>('core', 'apps')
			.filter(({ type }) => type === 'link')
			.map((app) => ({ ...app, label: app.name, default: app.default && app.id === enforcedDefaultApp }))

		/**
		 * Check if a custom app order is used or the default is shown
		 */
		const hasCustomAppOrder = ref(!Array.isArray(userAppOrder) || Object.values(userAppOrder).length > 0)

		/**
		 * Track if the app order has changed, so the user can be informed to reload
		 */
		const hasAppOrderChanged = computed(() => initialAppOrder.some(({ id }, index) => id !== appOrder.value[index].id))

		/** ID of the "app order has changed" NcNodeCard, used for the aria-details of the apporder */
		const elementIdAppOrderChanged = 'theming-apporder-changed-infocard'

		/** ID of the "you can not change the default app" NcNodeCard, used for the aria-details of the apporder */
		const elementIdEnforcedDefaultApp = 'theming-apporder-changed-infocard'

		/**
		 * The aria-details value of the app order selector
		 * contains the space separated list of element ids of NcNoteCards
		 */
		const ariaDetailsAppOrder = computed(() => (hasAppOrderChanged.value ? `${elementIdAppOrderChanged} ` : '') + (enforcedDefaultApp ? elementIdEnforcedDefaultApp : ''))

		/**
		 * The current apporder (sorted by user)
		 */
		const appOrder = ref([...initialAppOrder])

		/**
		 * Update the app order, called when the user sorts entries
		 * @param value The new app order value
		 */
		const updateAppOrder = (value: IApp[]) => {
			const order: IAppOrder = {}
			value.forEach(({ app, id }, index) => {
				order[id] = { order: index, app }
			})

			saveSetting('apporder', order)
				.then(() => {
					appOrder.value = value as never
					hasCustomAppOrder.value = true
				})
				.catch((error) => {
					console.warn('Could not set the app order', error)
					showError(t('theming', 'Could not set the app order'))
				})
		}

		/**
		 * Reset the app order to the default
		 */
		const resetAppOrder = async () => {
			try {
				await saveSetting('apporder', [])
				hasCustomAppOrder.value = false

				// Reset our app order list
				const { data } = await axios.get<IOCSResponse<INavigationEntry[]>>(generateOcsUrl('/core/navigation/apps'), {
					headers: {
						'OCS-APIRequest': 'true',
					},
				})
				appOrder.value = data.ocs.data.map((app) => ({ ...app, label: app.name, default: app.default && app.app === enforcedDefaultApp }))
			} catch (error) {
				console.warn(error)
				showError(t('theming', 'Could not reset the app order'))
			}
		}

		const saveSetting = async (key: string, value: unknown) => {
			const url = generateOcsUrl('apps/provisioning_api/api/v1/config/users/{appId}/{configKey}', {
				appId: 'core',
				configKey: key,
			})
			return await axios.post(url, {
				configValue: JSON.stringify(value),
			})
		}

		return {
			appOrder,
			updateAppOrder,
			resetAppOrder,

			enforcedDefaultApp,
			hasAppOrderChanged,
			hasCustomAppOrder,

			ariaDetailsAppOrder,
			elementIdAppOrderChanged,
			elementIdEnforcedDefaultApp,

			t,
		}
	},
})
</script>

<style scoped lang="scss">
.user-app-menu-order {
	margin-block: 12px;
}
</style>
