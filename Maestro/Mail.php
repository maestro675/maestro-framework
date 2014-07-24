<?php
/**
 * Description of Maestro_Mail
 *
 * @author maestro
 */
require_once "PHPMailer/class.phpmailer.php";
require_once "PHPMailer/class.pop3.php";

class Maestro_Mail
{
    private static $_instance;

	/**
	 * отправляет письмо по указанному адресу через SMTP порт
	 * в случае удачного завершения возвращает пустую строку
	 *
	 *
	 * @param string $to_user
	 * @param string $subject
	 * @param string $text
	 * @param string $is_html
	 * @return string
	 */
	public static function send( $to_user, $subject, $text, $is_html = false )
	{
		$instance = self::getInstance();

		$res = '';

		$mail = new PHPMailer();
		$mail->Host     = Maestro_App::getConfig()->Mail->host;
		$mail->Mailer   = Maestro_App::getConfig()->Mail->mailer;

		$mail->ContentType = ($is_html) ? "text/html" : "text/plain";
		$mail->Charset = "utf-8";
		$mail->IsHTML($is_html);

		$mail->From     = Maestro_App::getConfig()->Mail->email;
		$mail->FromName = Maestro_App::getConfig()->Mail->name;
		$mail->Subject  =	$subject;
		$mail->Body     = $text;
		$mail->AddAddress($to_user);

		if(!$mail->Send())
		{
			logger('error', "There has been a mail error sending to ".$to_user);
		}
		$res = $mail->ErrorInfo;
		$mail->ClearAddresses();
		$mail->ClearAttachments();

		return $res;
	}

	/**
	*
	*/
	private function __construct () {}

	/**
	*
	*/
	private function __clone () {}

	/**
	* Return the single instance of object
	*
	* @return object
	*/
	public static function &getInstance()
	{
		if(!isset(self::$_instance))
			self::$_instance = new self;
		return self::$_instance;
	}
}
?>
