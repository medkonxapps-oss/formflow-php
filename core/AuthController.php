<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * HTTP handlers for authentication routes.
 */
class AuthController
{
  /** @var array<string, mixed> */
  private array $config;

  private Auth $auth;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(array $config)
  {
    $this->config = $config;
    $this->auth = new Auth($config);
  }

  public function login(): void
  {
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token. Please try again.');
      set_old($_POST);
      redirect('/login');
    }

    $email = (string) ($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

    if ($this->auth->requiresCaptcha($email)) {
      $honeypot = trim((string) ($_POST['website'] ?? ''));
      if ($honeypot !== '') {
        flash('error', 'Invalid email or password.');
        set_old(['email' => $email]);
        redirect('/login');
      }
    }

    $result = $this->auth->attemptLogin($email, $password, $ip, $userAgent, $remember);

    if (!$result['success']) {
      flash('error', $result['error'] ?? 'Invalid email or password.');
      set_old(['email' => $email]);

      if (!empty($result['requires_captcha'])) {
        $_SESSION['_requires_captcha'] = true;
      }

      redirect('/login');
    }

    clear_old();
    unset($_SESSION['_requires_captcha']);
    Csrf::rotate();
    flash('success', 'Welcome back!');
    redirect('/admin');
  }

  public function logout(): void
  {
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/login');
    }

    $this->auth->logout();
    Csrf::rotate();
    flash('success', 'You have been signed out.');
    redirect('/login');
  }

  public function forgotPassword(): void
  {
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token. Please try again.');
      set_old($_POST);
      redirect('/forgot-password');
    }

    $email = (string) ($_POST['email'] ?? '');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    $result = $this->auth->requestPasswordReset($email, $ip);

    clear_old();
    Csrf::rotate();
    flash('success', $result['message']);
    redirect('/forgot-password');
  }

  public function resetPassword(): void
  {
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token. Please try again.');
      redirect('/login');
    }

    $token = (string) ($_POST['token'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirmation'] ?? '');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    if ($password !== $passwordConfirm) {
      flash('error', 'Passwords do not match.');
      redirect('/reset-password/' . urlencode($token));
    }

    $result = $this->auth->resetPassword($token, $password, $ip);

    if (!$result['success']) {
      flash('error', $result['error'] ?? 'Unable to reset password.');
      redirect('/reset-password/' . urlencode($token));
    }

    Csrf::rotate();
    flash('success', 'Your password has been reset. Please sign in.');
    redirect('/login');
  }

  public function acceptInvite(): void
  {
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/login');
    }

    $token = (string) ($_POST['token'] ?? '');
    $name = trim((string) ($_POST['name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirmation'] ?? '');

    $invites = new InviteRepository($this->config);
    $invite = $invites->findValidByToken($token);
    if ($invite === null) {
      flash('error', 'This invite link is invalid or has expired.');
      redirect('/login');
    }

    if ($password !== $passwordConfirm) {
      flash('error', 'Passwords do not match.');
      redirect('/invite/' . rawurlencode($token));
    }

    $result = $this->auth->register($name, (string) $invite['email'], $password, (string) $invite['role'], true);
    if (!$result['success']) {
      flash('error', $result['error'] ?? 'Could not create account.');
      redirect('/invite/' . rawurlencode($token));
    }

    $invites->markAccepted((int) $invite['id']);
    $this->auth->attemptLogin((string) $invite['email'], $password, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    Csrf::rotate();
    flash('success', 'Welcome to FormFlow.');
    redirect('/admin');
  }
}
