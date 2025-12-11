<?php

namespace App\Enum;

enum CertificateRouteAccess: string
{
  // RADSECPROXY
  case RADSECPROXY_UPLOAD = 'radsecproxy_upload';
  case RADSECPROXY_CONFIG = 'radsecproxy_config';
  case RADSECPROXY_TEST = 'radsecproxy_test';

  // FREERADIUS
  case FREERADIUS_SELECTION = 'freeradius_selection';
  case FREERADIUS_UPLOAD = 'freeradius_upload';
  case FREERADIUS_AUTO_RENEW = 'freeradius_auto_renew';
  case FREERADIUS_CONFIG = 'freeradius_config';
  case FREERADIUS_TEST = 'freeradius_test';

  public function routeName(): string
  {
    return match ($this) {
      // RADSECPROXY
      self::RADSECPROXY_UPLOAD =>
      'admin_dashboard_settings_certs_radsecproxy_upload',

      self::RADSECPROXY_CONFIG =>
      'admin_dashboard_settings_certs_radsecproxy_config',

      self::RADSECPROXY_TEST =>
      'admin_dashboard_settings_certs_radsecproxy_test',


      // FREERADIUS
      self::FREERADIUS_SELECTION =>
      'admin_dashboard_settings_certs_management_freeradius_selection',

      self::FREERADIUS_UPLOAD =>
      'admin_dashboard_settings_certs_freeradius_upload',

      self::FREERADIUS_AUTO_RENEW =>
      'admin_dashboard_settings_certs_freeradius_auto_renew',

      self::FREERADIUS_CONFIG =>
      'admin_dashboard_settings_certs_freeradius_config',

      self::FREERADIUS_TEST =>
      'admin_dashboard_settings_certs_freeradius_test',
    };
  }

  public static function orderedStages(): array
  {
    return [
      // RADSECPROXY
        self::RADSECPROXY_UPLOAD,
        self::RADSECPROXY_CONFIG,
        self::RADSECPROXY_TEST,

      // FREERADIUS
        self::FREERADIUS_SELECTION,
        self::FREERADIUS_UPLOAD,
        self::FREERADIUS_AUTO_RENEW,
        self::FREERADIUS_CONFIG,
        self::FREERADIUS_TEST,
    ];
  }

  public function phase(): string
  {
    return match ($this) {
      self::RADSECPROXY_UPLOAD,
      self::RADSECPROXY_CONFIG,
      self::RADSECPROXY_TEST
      => 'radsecproxy',

      self::FREERADIUS_SELECTION,
      self::FREERADIUS_UPLOAD,
      self::FREERADIUS_AUTO_RENEW,
      self::FREERADIUS_CONFIG,
      self::FREERADIUS_TEST
      => 'freeradius',
    };
  }
}
