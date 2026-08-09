<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 2.4.0
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exports filtered log entries.
 *
 * @since 2.4.0
 */
class LoggingDownload {

	/**
	 * Triggers a .log download for the currently filtered logs.
	 *
	 * @since 2.4.0
	 *
	 * @param Logging              $logging The logging page instance.
	 * @param array<string, mixed> $filters The active filters.
	 */
	public function download( Logging $logging, array $filters ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to export logs.', 'scouting-openid-connect' ) );
		}

		check_admin_referer( 'scouting_oidc_logs_filter', 'scouting_oidc_logs_filter_nonce' );

		$filename = 'scouting-oidc-logs-' . current_time( 'd-m-Y-H-i-s' ) . '.log';

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$current_user         = wp_get_current_user();
		$current_user_id      = isset( $current_user->ID ) ? (int) $current_user->ID : 0;
		$current_user_display = isset( $current_user->display_name ) ? $current_user->display_name : 'Unknown';

		$output  = '';
		$output .= "# Scouting OIDC logs export by user ID {$current_user_id} ({$current_user_display})\n";
		$output .= '# Website: ' . home_url() . "\n";
		$output .= '# UTC time of export: ' . gmdate( 'Y-m-d H:i:s' ) . "\n";
		$output .= '# WordPress time of export: ' . current_time( 'Y-m-d H:i:s e' ) . "\n\n";
		$output .= "# Filters applied:\n";
		foreach ( $filters as $key => $value ) {
			if ( 'user_id' === $key ) {
				if ( 0 === $value ) {
					$display = '';
				} else {
					$user_info = get_userdata( (int) $value );
					if ( false !== $user_info ) {
						$display = (int) $value . ' (' . $user_info->display_name . ')';
					} else {
						$display = (string) $value;
					}
				}
			} elseif ( 'sol_id' === $key ) {
				$user_info = get_user_by( 'login', (string) $value );
				if ( false !== $user_info ) {
					$display = (string) $value . ' (' . $user_info->display_name . ')';
				} else {
					$display = (string) $value;
				}
			} elseif ( is_array( $value ) ) {
					$display = '(' . implode( ', ', $value ) . ')';
			} else {
				$display = (string) $value;
			}

			$output .= "# - {$key}: {$display}\n";
		}
		$output .= "\n";

		$sorting = array(
			'orderby' => 'id',
			'order'   => 'asc',
		);

		$output .= "Format: [created_at] [level] [component] [user_id] [sol_id] message\n\n";

		// Fetch and output logs.
		$rows    = $logging->get_logs( $filters, $sorting );
		$padding = $this->determine_padding( $rows );
		if ( empty( $rows ) ) {
			$output .= "# No logs found for the applied filters.\n";
		} else {
			foreach ( $rows as $row ) {
				$output .= $this->format_log_line( $row, $padding );
			}
		}

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text log file download, not HTML output.
		echo $output;
		exit;
	}

	/**
	 * Determines the necessary padding length for a given field based on the log rows.
	 *
	 * @since 2.4.0
	 *
	 * @param array<int, array<string, mixed>> $rows The log rows.
	 * @return array<string, int>.
	 */
	private function determine_padding( array $rows ): array {
		$max_level_length     = 0;
		$max_component_length = 0;
		$max_user_id_length   = 0;
		$max_sol_id_length    = 0;

		foreach ( $rows as $row ) {
			$level_length = isset( $row['level'] ) ? strlen( (string) $row['level'] ) : 0;
			if ( $level_length > $max_level_length ) {
				$max_level_length = $level_length;
			}

			$component_length = isset( $row['component'] ) ? strlen( (string) $row['component'] ) : 0;
			if ( $component_length > $max_component_length ) {
				$max_component_length = $component_length;
			}

			$user_id_length = isset( $row['user_id'] ) ? strlen( (string) $row['user_id'] ) : 0;
			if ( $user_id_length > $max_user_id_length ) {
				$max_user_id_length = $user_id_length;
			}

			$sol_id_length = isset( $row['sol_id'] ) ? strlen( (string) $row['sol_id'] ) : 0;
			if ( $sol_id_length > $max_sol_id_length ) {
				$max_sol_id_length = $sol_id_length;
			}
		}

		// Set minimum padding lengths.
		return array(
			'created_at' => 23, // "dd-mm-yyyy hh:mm:ss.fff"
			'level'      => $max_level_length,
			'component'  => $max_component_length,
			'user_id'    => $max_user_id_length,
			'sol_id'     => $max_sol_id_length,
		);
	}

	/**
	 * Formats one log row as a single .log line.
	 *
	 * @since 2.4.0
	 *
	 * @param array<string, mixed> $row The log row.
	 * @param array<string, int>   $padding The column padding.
	 * @return string String value.
	 */
	private function format_log_line( array $row, array $padding ): string {
		// Render the stored UTC timestamp in the current site timezone.
		$created_at = Logger::scouting_oidc_format_utc_datetime_for_site( (string) ( $row['created_at'] ?? '' ) );
		$level      = strtoupper( (string) ( $row['level'] ?? 'unknown' ) );
		$component  = strtoupper( (string) ( $row['component'] ?? 'unknown' ) );

		$user_id = isset( $row['user_id'] ) && null !== $row['user_id'] && '' !== $row['user_id'] && (int) $row['user_id'] > 0
			? (string) ( (int) $row['user_id'] )
			: ' ';

		$sol_id = isset( $row['sol_id'] ) && trim( (string) $row['sol_id'] ) !== ''
			? trim( (string) $row['sol_id'] )
			: ' ';

		$message = (string) ( $row['message'] ?? '' );
		$message = str_replace( array( "\r\n", "\r", "\n" ), '\\n', $message );
		if ( '' === $message ) {
			$message = '-';
		}

		// Fixed-width/padded fields for monospaced alignment in .log.
		$created_at_padded = str_pad( $created_at, $padding['created_at'] );
		$level_padded      = str_pad( $level, $padding['level'] );
		$component_padded  = str_pad( $component, $padding['component'] );
		$user_id_padded    = str_pad( $user_id, $padding['user_id'] );
		$sol_id_padded     = str_pad( $sol_id, $padding['sol_id'] );

		return "[$created_at_padded] [$level_padded] [$component_padded] [$user_id_padded] [$sol_id_padded] $message\n";
	}
}
