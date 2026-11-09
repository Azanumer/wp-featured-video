# WP Featured Video

Give any post a featured video — YouTube, Vimeo or an uploaded MP4 — and show it instead of the featured image on single posts. The player lazy-loads behind a play-button facade, so a video-heavy front page costs nothing until a visitor presses play.

## Features

- **YouTube, Vimeo and direct files** — paste a watch link, a `youtu.be` short link, a Vimeo link, or an MP4/WebM URL.
- **Lazy facade** — the featured image is shown with a play button; the iframe is injected only on click. No third-party player, cookies or tracking until the visitor opts in. (YouTube uses the privacy-enhanced `youtube-nocookie.com` domain; Vimeo uses `dnt=1`.)
- **Responsive 16:9 wrapper** — the player always fills its container.
- **Autoplay option** — muted autoplay for video-first posts (browsers require muted autoplay).
- **Shortcode** — `[featured_video]` places the video anywhere in the content, or `[featured_video id="123"]` for another post.
- **Filter** — `wfv_replace_thumbnail` lets your theme decide per post whether the image is replaced.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it from **Plugins**.
3. Edit a post, paste a video URL into the **Featured Video** box, and publish.
4. Tweak behaviour under **Settings → Featured Video**.

## Notes

- The facade is keyboard-accessible (Enter/Space to play).
- MP4 files use a native `<video>` element with `preload="none"` and the featured image as poster.
- Uninstalling deletes the settings option and all `_wfv_video_url` postmeta.

## License

MIT — see [LICENSE](LICENSE).
