<?php

namespace Tests\Unit;

use App\Models\AppSetting;
use App\Services\BaiduOCRService;
use App\Services\DeepSeekService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceSettingOverwriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deepseek_service_prioritizes_app_setting(): void
    {
        config(['services.deepseek.key' => 'env-key']);
        AppSetting::set('deepseek_api_key', 'db-key');

        $service = new DeepSeekService;

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('apiKey');
        $property->setAccessible(true);

        $this->assertEquals('db-key', $property->getValue($service));
    }

    public function test_baidu_ocr_service_prioritizes_app_setting(): void
    {
        config(['services.baidu.ocr.token' => 'env-token']);
        AppSetting::set('baidu_ocr_token', 'db-token');

        $service = new BaiduOCRService;

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('token');
        $property->setAccessible(true);

        $this->assertEquals('db-token', $property->getValue($service));
    }

    public function test_services_fallback_to_env_if_setting_missing(): void
    {
        config(['services.deepseek.key' => 'env-key']);
        config(['services.baidu.ocr.token' => 'env-token']);

        // Ensure no settings in DB
        AppSetting::whereIn('key', ['deepseek_api_key', 'baidu_ocr_token'])->delete();

        $dsService = new DeepSeekService;
        $baiduService = new BaiduOCRService;

        $dsReflection = new \ReflectionClass($dsService);
        $dsProperty = $dsReflection->getProperty('apiKey');
        $dsProperty->setAccessible(true);

        $baiduReflection = new \ReflectionClass($baiduService);
        $baiduProperty = $baiduReflection->getProperty('token');
        $baiduProperty->setAccessible(true);

        $this->assertEquals('env-key', $dsProperty->getValue($dsService));
        $this->assertEquals('env-token', $baiduProperty->getValue($baiduService));
    }
}
