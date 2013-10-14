<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * Dependencies
 */
require_once('includes/mailchimp/MCAPI.class.php');

/**
 * MailChimp Plugin
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Plugin
 * @author		Chris Lock
 * @link		http://paramoredigital.com/chris
 */

$plugin_info = array(
	'pi_name'		=> 'MailChimp',
	'pi_version'	=> '1.0',
	'pi_author'		=> 'Chris Lock',
	'pi_author_url'	=> 'http://paramoredigital.com/chris',
	'pi_description'=> 'A simple ExpressionEngine plugin to add users to MailChimp',
	'pi_usage'		=> Mailchimp::usage()
);


class Mailchimp {
	/**
	 * Reference to the EE superglobal
	 * @var object
	 */
	public $EE;

	/**
	 * EE tags returned
	 * @var string
	 */
	public $return_data;

	/**
	 * MailChimp email type
	 */
	const EMAIL_TYPE = 'html';
	
	/**
	 * Send opt in email
	 */
	const DOUBLE_OPTIN = false;
	
	/**
	 * Updated contact instead of trhowing an error
	 */
	const UPDATE_EXISTING = true;
    
	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->EE =& get_instance();
	}
	
	/**
	 * Adds or updates a subscriber in MailChimp
	 * @param string api_key MailChimp API key
	 * @param string list_id Comma seperated list of MailChimp list ids
	 * @param string email Email address for subscriber
	 * @param string first_name First name of subscriber
	 * @param string last_name Last name of subscriber
	 * @param string address Address fields
	 * @param string custom Custom fields
	 * @return datatype description
	 */
	public function add_update_subscriber()
	{
		$api_key = $this->EE->TMPL->fetch_param('api_key', NULL);
		$list_ids = array_filter(array_unique(explode(',', $this->EE->TMPL->fetch_param('list_id', NULL))));
		$email = $this->EE->TMPL->fetch_param('email', NULL);
		$first_name = $this->EE->TMPL->fetch_param('first_name', NULL);
		$last_name = $this->EE->TMPL->fetch_param('last_name', NULL);
		$address_fields = $this->get_address_fields();
		$custom_fields = $this->get_custom_fields();

		$mailchimp_response_tags = $this->send_mailchimp_add_update_subscriber(
			$api_key,
			$list_ids,
			$email,
			$first_name,
			$last_name,
			$address_fields,
			$custom_fields
		);

		return $this->EE->TMPL->parse_variables(
			$this->EE->TMPL->tagdata,
			$mailchimp_response_tags
		);
	}

    public function get_subscriber_info()
    {
        $api_key = $this->EE->TMPL->fetch_param('api_key', NULL);
        $email = isset($_GET['email_address']) ? $_GET['email_address'] : NULL;

        $member_info = array();

        $member_lists = $this->get_mailchimp_lists_for_email($api_key, $email);

        if (sizeof($member_lists) > 0)
            $member_info = $this->get_mailchimp_member_info($api_key, $member_lists[0], $email);

        $vars = $this->build_subscriber_info($member_lists, $member_info);

//        exit(var_dump($vars));

        return $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $vars);
    }

    private function build_subscriber_info($lists, $member_info)
    {
        $return_data = array();

        if (empty($lists))
            return $return_data;

        $list_data = array();
        $member_data = array();

        foreach ($lists as $list)
            $list_data[] = array('list_id' => $list);

        $list_data = array('lists' => $list_data);

        if ( isset($member_info['data']) && isset($member_info['data'][0]) && isset($member_info['data'][0]['merges']) )
            $member_data = array(
                'email' => $member_info['data'][0]['merges']['EMAIL'],
                'first_name' => $member_info['data'][0]['merges']['FNAME'],
                'last_name' => $member_info['data'][0]['merges']['LNAME'],
                'address' => $member_info['data'][0]['merges']['ADDRESS'],
                'zip' => $member_info['data'][0]['merges']['ZIP'],
                'country' => $member_info['data'][0]['merges']['COUNTRY']
            );

        return array(array_merge($list_data, $member_data));
    }

	/**
	 * Returns a key/value array of address parameters
	 * that begin with "address:"
	 * @return array $custom_fields
	 * @author Jesse Bunch
	*/
	private function get_address_fields()
	{
		$all_params = $this->EE->TMPL->tagparams;
		$address_fields = array();

		if (is_array($all_params) && count($all_params))
			foreach ($all_params as $key => $val)
				if (strncmp($key, 'address:', 8) == 0)
					$address_fields[substr($key, 8)] = $val;

		return $address_fields;
	}

	/**
	 * Returns an associative array of tag paramters that
	 * begin with "custom:"
	 * @return array $custom_fields
	 * @author Jesse Bunch
	*/
	private function get_custom_fields()
	{
		$all_params = $this->EE->TMPL->tagparams;
		$custom_fields = array();

		if (is_array($all_params) && count($all_params))
			foreach ($all_params as $key => $val)
				if (strncmp($key, 'custom:', 7) == 0)
					$custom_fields[substr($key, 7)] = $val;

		return $custom_fields;
	}

	/**
	 * Sends request to adds or updates a subscriber in Campaign Monitor
	 * @param string MailChimp API key
	 * @param array MailChimp list ids
	 * @param string Email address for subscriber
	 * @param string First name of subscriber
	 * @param string Last name of subscriber
	 * @param array Address Fields
	 * @param array Custom Fields
	 * @return array Response tags for EE
	*/
	private function send_mailchimp_add_update_subscriber($api_key, $list_ids, $email, $first_name, $last_name, $address_fields, $custom_fields)
	{
		$response = array();

		$merge_vars = array_merge(
			array(
				'EMAIL' => $email,
				'FNAME' => $first_name,
				'LNAME' => $last_name
			),
			$this->convert_address_fields_to_merge_vars(
				$address_fields
			),
			$this->convert_custom_fields_to_merge_vars(
				$custom_fields
			)
		);

		$mailchimp_api = new MCAPI($api_key);

		foreach ($list_ids as $list_id) {
			$mailchimp_api->listSubscribe(
				$list_id,
				$email,
				$merge_vars,
				self::EMAIL_TYPE,
				self::DOUBLE_OPTIN,
				self::UPDATE_EXISTING
			);

			$response = $this->add_api_response_to_ee_repsonse($mailchimp_api, $response);
		}

		return $response;
	}

    private function get_mailchimp_lists_for_email($api_key, $email)
    {
        $mailchimp_api = new MCAPI($api_key);

        return $mailchimp_api->listsForEmail($email);
    }

    private function get_mailchimp_member_info($api_key, $list_id, $email)
    {
        $mailchimp_api = new MCAPI($api_key);

        return $mailchimp_api->listMemberInfo($list_id, $email);
    }


	/**
	 * Converts an associative array of address fields
	 * to an address field array for merge vars
	 * @param array Address fields
	 * @return array Address merge variable
	*/
	private function convert_address_fields_to_merge_vars($address_fields)
	{
		$address_merge_var = array(
			'ADDRESS' => array(
				'addr1' => '',
				'addr2' => '',
				'city' => '',
				'state' => '',
				'zip' => '',
				'country' => ''
			)
		);

		foreach ($address_fields as $key => $value)
			if (array_key_exists($key, $address_merge_var['ADDRESS']))
				$address_merge_var['ADDRESS'][$key] = $value;

		return $address_merge_var;
	}

	/**
	 * Converts an associative array of custom fields
	 * to merge vars
	 * @param array Custom fields
	 * @return array Merge variables
	*/
	private function convert_custom_fields_to_merge_vars($custom_fields)
	{
		$merge_vars = array();

		foreach ($custom_fields as $key => $value)
			$merge_vars[strtoupper($key)] = $value;

		return $merge_vars;
	}

	/**
	 * Converts MailChimp API object response to EE tags
	 * @param object MailChimp API object from MCAPI
     * @param array response
     *
	 * @return array Response tags for EE
	*/
	private function add_api_response_to_ee_repsonse($mailchimp_api, $response)
	{
		if (! count($response))
			$response = array(
				array(
					'success' => true,
					'error_string' => null
				)
			);

		if ($mailchimp_api->errorCode) {
			$response[0]['success'] = false;
			$error_string
				= '<br>Code = ' . $mailchimp_api->errorCode
				. '<br>Message = ' . $mailchimp_api->errorMessage;
			$response[0]['error_string'] .= $error_string;
			
			$this->report_error($error_string);
		}

		return $response;
	}

	/**
	 * Creates developer log and sends email for error
	 * @param string Error string from Mail Chimp
	 * @return void
	*/
	private function report_error($error_string)
	{
		$this->EE->load->library('logger');
		$this->EE->logger->developer('MailChimp Form Error: ' . $error_string);

		$headers = 'From: error@snohomish.org' . "\r\n" .
			'Reply-To: error@snohomish.org' . "\r\n" .
			'X-Mailer: PHP/' . phpversion();

		mail('jburke@paramoredigital.com', 'Snohomish MailChimp Form Error', $error_string, $headers);
	}
	
	/**
	 * Plugin Usage
	 * @return string Usage
	 */
	public static function usage()
	{
		ob_start();
			$read_me = file_get_contents(dirname(__file__) . '/README.md');
			echo($read_me);
			$buffer = ob_get_contents();
		ob_end_clean();

		return $buffer;
	}
}


/* End of file pi.mailchimp.php */
/* Location: /system/expressionengine/third_party/mailchimp/pi.mailchimp.php */