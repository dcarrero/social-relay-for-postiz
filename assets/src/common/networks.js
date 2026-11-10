/**
 * Display name and accent colour of each Postiz provider identifier.
 * Text and colour only: no third-party logos.
 */
const NETWORKS = {
	x: { label: 'X', color: '#000000' },
	linkedin: { label: 'LinkedIn', color: '#0A66C2' },
	'linkedin-page': { label: 'LinkedIn', color: '#0A66C2' },
	bluesky: { label: 'Bluesky', color: '#1185FE' },
	mastodon: { label: 'Mastodon', color: '#6364FF' },
	threads: { label: 'Threads', color: '#000000' },
	instagram: { label: 'Instagram', color: '#D62976' },
	'instagram-standalone': { label: 'Instagram', color: '#D62976' },
	facebook: { label: 'Facebook', color: '#1877F2' },
	pinterest: { label: 'Pinterest', color: '#E60023' },
	tiktok: { label: 'TikTok', color: '#000000' },
	youtube: { label: 'YouTube', color: '#FF0000' },
	telegram: { label: 'Telegram', color: '#229ED9' },
	discord: { label: 'Discord', color: '#5865F2' },
	gmb: { label: 'Google Business', color: '#4285F4' },
	reddit: { label: 'Reddit', color: '#FF4500' },
};

/**
 * Network info for an identifier.
 *
 * @param {string} identifier Postiz provider identifier.
 * @return {{label: string, color: string}} Network.
 */
export function network( identifier ) {
	return NETWORKS[ identifier ] || { label: identifier, color: '#5B3DF5' };
}
