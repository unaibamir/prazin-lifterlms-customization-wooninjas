<?php
/**
 * Admin Settings Page, Checkout Tab
 *
 * @since 3.0.0
 * @version 3.35.1
 */

defined( 'ABSPATH' ) || exit;

if( !class_exists("LLMS_Settings_Page") ) {
	require_once LLMS_PLUGIN_DIR . '/includes/admin/settings/class.llms.settings.page.php';
}

/**
 * Admin Settings Page, Checkout Tab
 *
 * @since 3.0.0
 * @since 3.30.3 Fixed spelling errors.
 * @since 3.35.1 Verify nonce when saving.
 */
class LLMS_Woo_Settings extends LLMS_Settings_Page {

	/**
	 * Allow settings page to determine if a rewrite flush is required
	 *
	 * @var      boolean
	 * @since    3.0.4
	 * @version  3.10.0
	 */
	protected $flush = true;

	/**
	 * Constructor
	 * executes settings tab actions
	 *
	 * @since    3.0.4
	 * @version  3.17.5
	 */
	public function __construct() {

		$this->id    = 'woo-setting';
		$this->label = __( 'Renewal Discount', 'lifterlms' );

		add_filter( 'lifterlms_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
		add_action( 'lifterlms_sections_' . $this->id, array( $this, 'output_sections_nav' ) );
		add_action( 'lifterlms_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'lifterlms_settings_save_' . $this->id, array( $this, 'save' ) );

	}

	/**
	 * Get settings array
	 *
	 * @return   array
	 * @since    3.0.4
	 * @version  3.17.5
	 */
	public function get_settings() {

		$curr_section = $this->get_current_section();

		return apply_filters( 'lifterlms_woo_settings', $this->get_settings_default() );

	}

	/**
	 * Retrieve the default checkout settings for the main section
	 *
	 * @since 3.17.5
	 * @since 3.30.3 Fixed spelling errors.
	 *
	 * @return array
	 */
	private function get_settings_default() {

		return array(

			array(
				'class' => 'top',
				'id'    => 'woo_membership_basic_options_start',
				'type'  => 'sectionstart',
			),

			array(
				'id'    => 'course_options',
				'title' => __( 'Wooninjas Membership Renewal Discount Settings', 'lifterlms' ),
				'type'  => 'title',
			),

			array(
				'title'   => __( 'Membership Recurring Discount', 'lifterlms' ),
				'desc'    => __( 'Enable/Disable Membership Recurring Discount.', 'lifterlms' ),
				'id'      => 'lifterlms_woo_membership_recurring_discount',
				'type'    => 'checkbox',
				'default' => 'no',
			),

			array(
				'title'   => __( 'Discount Rate', 'lifterlms' ),
				'class'   => 'tiny',
				'desc'    => __( 'Choose the discount amount membership recurring discount.', 'lifterlms' ).
					'<br>' . __( 'Enter only number without currency symbol. ', 'lifterlms' ),
				'id'      => 'lifterlms_woo_membership_recurring_discount_price',
				'type'    => 'number',
				'default' => '',
				'custom_attributes' => array(
					"min"	=> 0
				)
			),

			array(
				'title'   => __( 'Resubscribe Duration Unit', 'lifterlms' ),
				'class'   => 'tiny',
				'desc'    => __( 'Choose the number unit.', 'lifterlms' ),
				'id'      => 'lifterlms_woo_membership_duration_unit',
				'type'    => 'number',
				'default' => '',
				'custom_attributes' => array(
					"min"	=> 1
				)
			),

			array(
				'title'   => __( 'Resubscribe Duration Frequency', 'lifterlms' ),
				'class'   => 'tiny',
				'desc'    => __( 'Choose day, month or year', 'lifterlms' ),
				'id'      => 'lifterlms_woo_membership_duration_frequency',
				'type'    => 'select',
				'default' => 'month',
				'options' => array(
					"day"	=> 	'day',
					"month"	=> 	'month',
					'year'	=>	'year'
				)
			),

			array(
				'type' => 'sectionend',
				'id'   => 'woo_membership_basic_options_end',
			),

			array(
				'type' => 'sectionstart',
				'id'   => 'woo_membership_email_options_start',
			),

			array(
				'title' => __( 'Discount Email Settings', 'lifterlms' ),
				'type'  => 'title',
				'desc'	=> __( 'This is the email message that is sent out when a membership is about to expire near 24 hours and asks users to apply discount on their membership renewl.', 'lifterlms') . '<br>'
				. __( 'Upon click of the %email_discount_link%, the discount will be automatically applied on future recurring charges.', 'lifterlms'),
				'id'    => 'checkout_settings_gateways_list_title',
			),

			array(
				'title'   => __( 'Send Email Before', 'lifterlms' ),
				'class'   => 'tiny',
				'desc'    => __( 'Choose the number of days the email will be sent to users.', 'lifterlms' ) 
				. '<br>' . __( 'Minimum of 1 day.', 'lifterlms' ),
				'id'      => 'lifterlms_woo_membership_before_email_days',
				'type'    => 'number',
				'default' => '',
				'custom_attributes' => array(
					"min"	=> 1
				)
			),

			array(
				'title'   => __( 'Email Subject', 'lifterlms' ),
				'class'   => 'large',
				/*'desc'    => __( 'Choose the discount rate in percentage (%) for membership recurring discount.'.
					'<br>' . __( 'Enter only number. Example: 20', 'lifterlms' ), 'lifterlms' ),*/
				'id'      => 'lifterlms_woo_discount_email_subject',
				'type'    => 'text',
				'default' => '',
			),

			array(
				'title'   => __( 'Email Heading', 'lifterlms' ),
				'class'   => 'large',
				/*'desc'    => __( 'Choose the discount rate in percentage (%) for membership recurring discount.'.
					'<br>' . __( 'Enter only number. Example: 20', 'lifterlms' ), 'lifterlms' ),*/
				'id'      => 'lifterlms_woo_discount_email_heading',
				'type'    => 'text',
				'default' => '',
			),

			array(
				'title'   => __( 'Email Content', 'lifterlms' ),
				'class'   => 'tiny',
				/*'desc'    => '<br>' . __( 'Choose the discount rate in percentage (%) for membership recurring discount.'.
					'<br>' . __( 'Enter only number. Example: 20', 'lifterlms' ), 'lifterlms' ),*/
				'id'      => 'lifterlms_woo_discount_email_content',
				'type'    => 'wpeditor',
				'default' => '',
				'class'	  => 'large',
				'editor_settings' => array(
					'teeny' => true,
				),
			),

			array(
				'title'   => __( 'Available Template Tags', 'lifterlms' ),
				'id'      => 'lifterlms_woo_available_tags',
				'type' 	  => 'custom-html',
				'value'   => $this->available_tags()
			),

			array(
				'type' => 'sectionend',
				'id'   => 'woo_membership_email_options_end',
			),


			array(
				'type' => 'sectionstart',
				'id'   => 'woo_membership_thankyou_email_options_start',
			),

			array(
				'title' => __( 'Thankyou Email Settings', 'lifterlms' ),
				'type'  => 'title',
				'desc'	=> __( 'This is the email message that is sent out when a membership is renewed/resubscribed.', 'lifterlms'),
				'id'    => 'woo_membership_thankyou_email_options_title',
			),

			array(
				'title'   => __( 'Email Subject', 'lifterlms' ),
				'class'   => 'large',
				/*'desc'    => __( 'Choose the discount rate in percentage (%) for membership recurring discount.'.
					'<br>' . __( 'Enter only number. Example: 20', 'lifterlms' ), 'lifterlms' ),*/
				'id'      => 'woo_membership_thankyou_email_subject',
				'type'    => 'text',
				'default' => '',
			),

			array(
				'title'   => __( 'Email Heading', 'lifterlms' ),
				'class'   => 'large',
				/*'desc'    => __( 'Choose the discount rate in percentage (%) for membership recurring discount.'.
					'<br>' . __( 'Enter only number. Example: 20', 'lifterlms' ), 'lifterlms' ),*/
				'id'      => 'woo_membership_thankyou_email_heading',
				'type'    => 'text',
				'default' => '',
			),

			array(
				'title'   => __( 'Email Content', 'lifterlms' ),
				'class'   => 'tiny',
				/*'desc'    => '<br>' . __( 'Choose the discount rate in percentage (%) for membership recurring discount.'.
					'<br>' . __( 'Enter only number. Example: 20', 'lifterlms' ), 'lifterlms' ),*/
				'id'      => 'woo_membership_thankyou_email_content',
				'type'    => 'wpeditor',
				'default' => '',
				'class'	  => 'large',
				'editor_settings' => array(
					'teeny' => true,
				),
			),

			array(
				'title'   => __( 'Available Template Tags', 'lifterlms' ),
				'id'      => 'lifterlms_woo_available_tags',
				'type' 	  => 'custom-html',
				'value'   => $this->available_tags("thankyou_email")
			),

			array(
				'type' => 'sectionend',
				'id'   => 'woo_membership_thankyou_email_options_end',
			),
		);

	}

	public function available_tags( $context = "discount_email" ) {

		ob_start();
		?>

		<table class="llms-tabletext-left size-large">
			<tbody>
				<tr>
					<th>
						<?php _e("Available Template Tags"); ?>
					</th>
					<td>
						<p class="description">The following template tags are available for use in all of the email settings.</p>
						<ul><li><em>%name%</em> - The full name of the member</li>
							<li><em>%username%</em> - The user name of the member on the site</li>
							<li><em>%useremail%</em> - The email address of the member</li>
							<li><em>%firstname%</em> - The first name of the member</li>
							<li><em>%lastname%</em> - The last name of the member</li>
							<li><em>%displayname%</em> - The display name of the member</li>
							<li><em>%blogname%</em> - The name of this website</li>
							<?php if( $context == "discount_email" ): ?>
								<li><em>%expiration%</em> - The expiration date of the member</li>
								<li><em>%discount_price% - The discounted price</em></li>
								<li><em>%email_discount_link%</em> - The link of applying discoint on email.</li>
								<li><em>%membership_name%</em> - The name of membership with plan.</li>
								<li><em>%membership_duration%</em> - The duration of renewal membership.</li>
							<?php endif; ?>
						</ul>
					</td>
				</tr>
			</tbody>
		</table>

		<?php
		return ob_get_clean();
	}

	/**
	 * Override default save method to save the display order of payment gateways
	 *
	 * @since 3.17.5
	 * @since 3.35.1 Verify nonce.
	 *
	 * @return   void
	 */
	public function save() {

		if ( ! llms_verify_nonce( '_wpnonce', 'lifterlms-settings' ) ) {
			return;
		}

		// save all custom fields
		parent::save();
	}

}

return new LLMS_Woo_Settings();
