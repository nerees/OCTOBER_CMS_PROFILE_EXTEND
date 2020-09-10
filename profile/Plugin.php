<?php namespace Nerijus\Profile;

use Backend;
use System\Classes\PluginBase;
use RainLab\User\Models\User as UserModel;
use RainLab\User\Controllers\Users as UsersController;
use Nerijus\Profile\Models\Profile as ProfileModel;

/**
 * Profile Plugin Information File
 */
class Plugin extends PluginBase
{

    public $require = ['RainLab.User'];
    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Profile',
            'description' => 'Plui-in exdends RainLab USER to store loyality cart data',
            'author'      => 'Nerijus',
            'icon'        => 'icon-leaf'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {

        UserModel::extend(function($model){

            $model->hasOne['profile'] = ['Nerijus\Profile\Models\Profile'];
            
            $model->fillable($model->getFillable() + [
                "saskaita",
                "korteles",
            ]);
			
			//ekstendinam validaciją ir pranešimus kortelės numeriui, panaudoju name lauka
			
			 // Extend the validation rules
			$model->rules['name'] = 'required|digits:19|unique:users,name';
			
			// Extend the attribute names ()
			$model->addDynamicProperty('attributeNames', [
				'password' => 'slaptažodis',
				'name' => 'kortelės numeris'
			]);
			
			// Extend the validation messages
			$model->addDynamicProperty('customMessages', [
				'password.required' => 'Paskyrai būtinas slaptažodis',
				'name.required' => 'Įveskite tinkamą kortelės numerį',
				'name.unique' => 'toks kortelės numeris jau registruotas',
				'name.digits' => 'reikalingi paskutiniai 9 kortelės skaičių'
			]);
			
        });

        UsersController::extendListColumns(function($list, $model) {
            if (!$model instanceof UserModel)
                return;

            $list->addColumns([
                'saskaita' => [
                    'label' => 'Sąskaita'
                ],
                'korteles' => [
                    'label' => 'Kortelės'
                ]
            ]);
        });

        UsersController::extendFormFields(function($form, $model, $context){

            if (!$model instanceof UserModel)
                return;

            if (!$model->exists)
                return;

            //ensures that a profile model always exists    
            ProfileModel::getFromUser($model);

            $form->addTabFields([
                'profile[saskaita]' => [
                    'label' => 'Sąskaita',
                    'tab' => 'Profile',
                    'type' => 'text',
                ],
                'profile[korteles]' => [
                    'label' => 'Kortelės',
                    'tab' => 'Profile',
                    'type' => 'text',
                ],
            ]);

            
        });

    }

    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return array
     */
    public function registerComponents()
    {
       // return []; // Remove this line to activate

      return [
            'Nerijus\Profile\Components\CardInfo' => 'cardinfo',
      ];
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return []; // Remove this line to activate

        return [
            'nerijus.profile.some_permission' => [
                'tab' => 'Profile',
                'label' => 'Some permission'
            ],
        ];
    }

    /**
     * Registers back-end navigation items for this plugin.
     *
     * @return array
     */
    public function registerNavigation()
    {
        return []; // Remove this line to activate

        return [
            'profile' => [
                'label'       => 'Profile',
                'url'         => Backend::url('nerijus/profile/cardinfo'),
                'icon'        => 'icon-leaf',
                'permissions' => ['nerijus.profile.*'],
                'order'       => 500,
            ],
        ];
    }
}
