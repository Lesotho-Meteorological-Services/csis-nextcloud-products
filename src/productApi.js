import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export async function fetchStructuredProductDefinition(type) {
	return axios.get(generateUrl('/apps/csis_products/products/{type}', { type }))
}

export async function generateStructuredProduct({ dir, type, values }) {
	return axios.post(generateUrl('/apps/csis_products/products/generate'), {
		dir,
		type,
		values,
	})
}
