<?php
class TextDomainCheck
{
	protected $error = array();
	protected $themename = "envato-licence-validate";

	// rules come from WordPress core tool makepot.php, modified by me to have domain info
	var $rules = array(
		'__' => array('string', 'domain'),
		'_e' => array('string', 'domain'),
		'_c' => array('string', 'domain'),
		'_n' => array('singular', 'plural', 'domain'),
		'_n_noop' => array('singular', 'plural', 'domain'),
		'_nc' => array('singular', 'plural', 'domain'),
		'__ngettext' => array('singular', 'plural', 'domain'),
		'__ngettext_noop' => array('singular', 'plural', 'domain'),
		'_x' => array('string', 'context', 'domain'),
		'_ex' => array('string', 'context', 'domain'),
		'_nx' => array('singular', 'plural', 'context', 'domain'),
		'_nx_noop' => array('singular', 'plural', 'context', 'domain'),
		'_n_js' => array('singular', 'plural', 'domain'),
		'_nx_js' => array('singular', 'plural', 'context', 'domain'),
		'esc_attr__' => array('string', 'domain'),
		'esc_html__' => array('string', 'domain'),
		'esc_attr_e' => array('string', 'domain'),
		'esc_html_e' => array('string', 'domain'),
		'esc_attr_x' => array('string', 'context', 'domain'),
		'esc_html_x' => array('string', 'context', 'domain'),
		'comments_number_link' => array('string', 'singular', 'plural', 'domain'),
	);

	// core names their themes differently
	var $exceptions = array('twentyten',  'twentyeleven',  'twentytwelve',  'twentythirteen',  'twentyfourteen',  'twentyfifteen',  'twentysixteen',  'twentyseventeen',  'twentyeighteen',  'twentynineteen',  'twentytwenty');

	function check($php_files, $css_files, $other_files)
	{
		global $data;

		$ret = true;
		$error = '';
		checkcount();

		// make sure the tokenizer is available
		if (!function_exists('token_get_all')) {
			return true;
		}

		$funcs = array_keys($this->rules);

		$domains = array();

		foreach ($php_files as $php_key => $phpfile) {
			$error = '';

			// tokenize the file
			$tokens = token_get_all($phpfile);

			$in_func = false;
			$in_sprintf = false;
			$args_started = false;
			$parens_balance = 0;
			$found_domain = false;

			foreach ($tokens as $token) {
				$string_success = false;
				if (is_array($token)) {

					list($id, $text) = $token;
					if ($text == "sprintf") {

						$in_sprintf = true;
						if ($parens_balance == 1) {

							$args_count++;
							$args[] = $text;
						}
					}
					//} else {
					if (!$in_sprintf) {

						if (T_STRING == $id && in_array($text, $funcs)) {


							$in_func = true;
							$func = $text;
							$parens_balance = 0;
							$args_started = false;
							$found_domain = false;
						} elseif (T_CONSTANT_ENCAPSED_STRING == $id) {

							if ($in_func && $args_started) {

								if (!isset($this->rules[$func][$args_count])) {
									// avoid a warning when too many arguments are in a function, cause a fail case
									$new_args = $args;
									$new_args[] = $text;
									$this->error[] = '<span class="tc-lead tc-warning">' . 'WARNING' . '</span>: '
										. sprintf(
											'Found a translation rrr function that is missing a text-domain. Function %1$s, with the arguments %2$s in file: <strong>' . $php_key . $token[2] . '</strong>',
											'<strong>' . $func . '</strong>',
											'<strong>' . implode(', ', $new_args) . '</strong>'
										);
								} else if ($this->rules[$func][$args_count] == 'domain') {
									// strip quotes from the domain, avoids 'domain' and "domain" not being recognized as the same
									$text = str_replace(array('"', "'"), '', $text);
									$domains[] = $text;
									$found_domain = true;
								}
								if ($parens_balance == 1) {

									$args_count++;
									$args[] = $text;
								}
							}
						}
						$token = $text;
					}
					//}
				} elseif ('(' == $token) {
					if (!$in_sprintf) {
						if ($parens_balance == 0) {
							$args = array();
							$args_started = true;
							$args_count = 0;
						}
						++$parens_balance;
					}
				} elseif (')' == $token) {
					if (!$in_sprintf) {
						--$parens_balance;
						if ($in_func && 0 == $parens_balance) {
							if (!$found_domain) {

								$args = implode(', ', $args);
								if (empty($args)) {
									$this->error[] = '<span class="tc-lead tc-warning">' .  'WARNING'   . '</span>: '
										. sprintf(
											'Found a 1 translation function that is missing a text-domain. Function %1$s, with empty arguments in file: <strong>' . ($php_key) . '</strong>',
											'<strong>' . $func . '</strong>'
										);
								} else {
									$this->error[] = '<span class="tc-lead tc-warning">' . 'WARNING'   . '</span>: '
										. sprintf(
											'Found a 2 translation function that is missing a text-domain. Function %1$s, with the arguments %2$s in file: <strong>' . ($php_key)  . '</strong>',
											'<strong>' . $func . '</strong>',
											'<strong>' . $args . '</strong>'
										);
								}
							}
							$in_func = false;
							$func = '';
							$args_started = false;
							$found_domain = false;
						}
					} else {

						$in_sprintf = false;
					}
				}
			}
		}

		$domains = array_unique($domains);
		// Now, Remove any empty values from array.
		$domains = array_filter($domains);

		$domainlist = implode(', ', $domains);
		$domainscount = count($domains);

		// ignore core themes and uploads on w.org for this one check
		if (!in_array($this->themename, $this->exceptions) && !defined('WPORGPATH')) {
			$correct_domain = ($data['Name']);
			if ($this->themename != $correct_domain) {
				$this->error[] = '<span class="tc-lead tc-warning">' . 'INFO'  . '</span>: '
					. sprintf("Your theme appears to be in the wrong directory for the theme name. The directory name should match the slug of the theme. This theme's recommended slug and text-domain is %s.", '<strong>' . $correct_domain . '</strong>');
			} elseif (!in_array($correct_domain, $domains)) {
				$this->error[] = '<span class="tc-lead tc-required">' .  'INFO'  . '</span>: '
					. sprintf("This theme text domain does not match the theme's slug. The text domain(s) used: %s ", '<strong>' . $domainlist . '</strong>. ')
					. sprintf("This theme's recommended slug / text-domain is %s ",  '<strong>' . $correct_domain . '</strong>');
			}
		}

		if ($domainscount > 1) {
			$this->error[] = '<span class="tc-lead tc-warning">' . 'INFO'  . '</span>: '
				.  'More than one text-domain is being used in this theme. Text domains that are unrelated to the theme are not allowed and must be removed. Packaged PHP libraries (i.e. TGMPA), frameworks and plugin template text domains are allowed.'
				. '<br>'
				. sprintf('The domains found are %s', '<strong>' . $domainlist . '</strong>');
		}

		if ($domainscount > 2) {
			$ret = false;
		}

		return $ret;
	}

	function getError()
	{
		return $this->error;
	}
}
