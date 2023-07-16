require 'capybara/rspec'
require 'selenium-webdriver'
require 'capybara-screenshot/rspec'

Capybara.server_host = Socket.ip_address_list.detect(&:ipv4_private?).ip_address

Capybara.register_driver :chrome do |app|
  browser_options = Selenium::WebDriver::Chrome::Options.new
  opts = {browser: :remote, options: browser_options, url: ENV.fetch('SELENIUM_URL', 'http://selenium:4444/wd/hub')}
  Capybara::Selenium::Driver.new(app, **opts)
end

Capybara::Screenshot.register_driver(:chrome) do |driver, path|
  driver.browser.save_screenshot(path)
end

Capybara.javascript_driver = :chrome
Capybara.default_driver = :chrome
Capybara.default_max_wait_time = 20
Capybara.enable_aria_label = true
Capybara.save_path = 'tmp/capybara'

module CapybaraHelpers
  APP_URL = ENV.fetch('DOMAIN', 'http://frontend:3000')

  def app_url(path)
    url = URI(APP_URL)
    url.path = path
    url
  end
end

RSpec.configure do |config|
  config.include CapybaraHelpers
end
