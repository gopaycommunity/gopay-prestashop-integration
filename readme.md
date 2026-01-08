# GoPay PrestaShop Integration

## Table of Contents

- [About the Project](#about-the-project)
    - [Built With](#built-with)
    - [Changelog](#changelog)
- [Development](#development)
    - [Prerequisites](#prerequisites)
    - [Installation](#installation)
    - [Run project](#run-project)
    - [Project Structure](#project-structure)
    - [Migrations](#migrations)
    - [Library update](#library-update)
    - [Testing](#testing)
- [Versioning](#versioning)
  - [Contribution](#contribution)
  - [Contribution process in details](#contribution-process-in-details)
  - [Branch consistency across repositories](#branch-consistency-across-repositories)
- [Deployment](#deployment)
- [Internationalization](#internationalization)
  - [Add or Update new language](#add-or-update-new-language)
- [Documentation](#documentation)
- [Other useful links](#other-useful-links)

## About The Project

GoPay payment gateway integration with the PrestaShop eCommerce platform.

### Built With

- [GoPay's PHP SDK for Payments REST API](https://github.com/gopaycommunity/gopay-php-api)
- [Composer](https://getcomposer.org/)

## Changelog
### 1.0.0
- PrestaShop and GoPay gateway integration.

### 1.0.1
- Fix of discount amount

### 1.0.2
- Update PrestaShop GoPay gateway module to support latest versions from v8.0.1 to v8.1.6

### 1.0.3
- Implemented Enhancements and New features:
    - Expanded payment Options: Added support for additional payment methods.
    - Improved localization logic: Revised the internal logic handling language selection for payment methods.
    - Minor visual update: Included updated logos for supported payment providers.
    - Dependency management: Project now requires manual installation of vendor dependencies.
    - Documentation: README documentation updated accordingly.

### 1.0.4
- Remove the Retry Payment option in the settings of GoPay gateway module.
- The module supports PrestaShop up to version 8.2.3.

### 1.0.5
- The module now supports PrestaShop up to version 9.0.1.

### 1.0.6
- Reordered GoPay credentials in settings
- Gateway now fully supports shipping when using [Packeta](https://github.com/Zasilkovna/prestashop) module
- Supports PrestaShop up to version 9.0.2

## Development

Running project on local machine for development and testing purposes.

### Prerequisites

- [PHP](https://www.php.net)
- [PrestaShop](https://www.prestashop.com/)
- [Docker Desktop](https://www.docker.com/get-started)
- [Docker Compose](https://docs.docker.com/compose/) _(is part of Docker Desktop)_

### Compatibility

The module is compatible with the following versions:
- **PrestaShop:** v8.0.1 – v9.0.2
- **PHP:** v8.1 or later

### Installation

### Run project

For local project execution, first install PrestaShop, then upload and configure the module by following the steps below:
1. Install the module through the PrestaShop modules screen.
   1. Download it from GitHub and uncompress it.
   2. Rename the folder to have the same name as the main php file “prestashopgopay”.
   3. Install vendor dependencies using Composer. Run the command `composer install` to add all necessary vendor dependencies.
   4. Compress the folder again.
   5. On "Modules and services" click on "Add a new module" and install the GoPay extension.
2. Activate the module through the modules screen.
3. Configure the module by providing goid, client id and secret to load the other options (follow these [steps](https://help.gopay.com/en/knowledge-base/gopay-account/gopay-business-account/signing-in-password-reset-activating-and-deactivating-the-payment-gateway/how-to-activate-the-payment-gateway) to activate the payment gateway and get goid, client id and secret).
4. Finally, choose the options you want to be available in the payment gateway (payment methods and banks must be enabled in your GoPay account).

### Project Structure

- **`controllers`**
  - **`admin`**
  - **`front`**
- **`includes`**
- **`translations`**
- **`vendor`**
- **`views`**
  - **`css`**
  - **`js`**
  - **`templates`**
    - **`admin`**
    - **`front`**
    - **`hook`**
- **`readme.md`**

### Migrations

### Library update
1. Open Terminal: Navigate to your project directory using Terminal.
2. Run composer update command for the library: Use the following command, replacing 'library-name' with the actual name of the library you want to update:
```sh
$ composer update vendor/library-name
```
For example:
```sh
$ composer update guzzlehttp/guzzle
```
Command will update the specified library to the latest version.

3. Review changes: After running the composer update command, review the changes made to your composer.lock file and your vendor directory. The composer.lock file will contain the exact versions of all libraries and dependencies installed in your project.
4. Test module: Once the libraries are updated, it's essential to thoroughly test the module to ensure that everything is working as expected with the updated dependencies.
5. Commit changes: Don't forget to commit the changes to composer.json, composer.lock, and vendor directory after updating library.
6. Update README (if necessary): If any significant changes occur due to the library updates, make sure to update your README file to reflect those changes. This could include new dependencies, updated requirements, or any other relevant information.

### Testing
1. Perform test transactions: Execute a variety of test transactions using different scenarios. Access the URL provided for all product [requirements](https://argo22.atlassian.net/wiki/spaces/GPY020/pages/2932703233/Product+requirements). Verify that the module accurately handles each scenario and delivers the correct behavior to the end-user.

2. Debug log: The current state of PrestaShop lacks easy-to-use tools for debugging and reviewing logs. To review logs, it is recommended to use Docker Log as a reference for errors and warnings. Alternatively, you can access logs from the back office under Advanced Parameters > Logs. Logs related to the PrestaShop environment can be found at /app/logs/prod.log.

3. Check order processing: Upon completing test transactions, confirm that orders are handled properly within PrestaShop. Ensure that order information, payment statuses, and transaction records are precisely documented and displayed in the PrestaShop admin dashboard.

4. Inspect and review the log: Examine the log file, which contains crucial information about errors, warnings, and other debug messages produced during the testing process. Pay close attention to any entries related to the functionality being tested.

5. Review transaction logs: In the PrestaShop admin panel, navigate to the payment gateway's log section for comprehensive transaction insights. This dedicated log section provides detailed records of all transactions processed through the payment gateway, offering valuable insights into payment statuses, transaction IDs, timestamps, and any potential errors encountered during the payment process.

6. Test compatibility: Ensuring compatibility with PrestaShop is our primary objective during the development and testing phases of the module. However, due to the diverse ecosystem of PrestaShop extensions and the unique configurations that users may employ, we cannot guarantee seamless compatibility with every extension or user setting.

7. Review error handling: Test the module's error handling capabilities by deliberately triggering errors, such as invalid payment credentials or network timeouts. Verify that error messages are clear, expected, and guide users toward resolution steps.

8. Note test results: Record your test findings, noting any issues like unexpected behaviors, warnings, errors, deprecated functions, and identified bugs along with their solutions. Keep detailed test notes for future reference and troubleshooting.

## Versioning

This module uses [SemVer](http://semver.org/) for versioning scheme.

### Contribution

- `master` - contains production code. You must not make changes directly to the master!
- `staging` - contains staging code. Pre-production environment for testing.
- `development` - contains development code.

### Contribution process in details

1. Use the development branch for the implementation.
2. Update corresponding readmes after the completion of the development.
3. Create a pull request and properly revise all your changes before merging.
4. Push into the development branch.
5. Upload to staging for testing.
6. When the feature is tested and approved on staging, pull you changes to master.

### Branch consistency across repositories
After implementing all alterations and updating the version, it is necessary to synchronize these updates with the GoPay GitHub repository. Ensure our repository mirrors any supplementary changes made by GoPay. Should there be new changes, perform the synchronization using the provided terminal command:
```sh
$ git push <remote> <source>:<destination>
```
- `remote`: This specifies the remote repository where you want to push your changes. This points to a remote Git repository, often hosted on a GitHub platform. The remote address, which should follow the format `git@github.com:organization/example-repository.git`, can be fetched from SSH GitHub.
- `source`: This represents the local branch you want to push to the remote repository. If you're using "development" as the source, it means you want to push the changes from the local "development" branch to the remote repository.
- `destination`: This denotes the branch in the remote repository where you want to push your changes. Since you're also using "development" as the destination branch, it means you are pushing changes from the local "development" branch to the remote "development" branch.

Upon completing synchronization, proceed to push the changes to the GoPay repository using the same terminal command, making sure to modify the `remote` and specify the `source` and `destination`.

## Deployment

Before deploy change Version in the `prestashopgopay.php`, then commit & push. Also check the minimum and maximum supported versions for PrestaShop. Staging site uses staging branch.

## Internationalization

### Add or Update new language

Add a new language on _'Add / Update a language'_ from tab _'IMPROVE/International/Translations'_. On _'Modify translations'_ choose _'Type of translation: Installed modules translations'_, _'Select your module: PrestaShop GoPay gateway'_ and _'Select your language: Language to be translated'_. Finally, Click on _'Modify'_, add the translations and click on _'Save'_.

## Documentation

## Other useful links