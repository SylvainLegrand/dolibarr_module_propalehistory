# Change Log
All notable changes to this project will be documented in this file.

## [Unreleased]

- FIX : Remove php exec call and use copy instead  *03/10/2022* - 2.5.1
- NEW : Option to keep version number on restore  *03/10/2022* - 2.5.0 [PR# Open-DSI](https://github.com/ATM-Consulting/dolibarr_module_propalehistory/pull/56)
  un nouveau paramètre permettant de restaurer une proposition commerciale tout en gardant le numéro de version
    ![image](https://user-images.githubusercontent.com/45359511/182880333-4a486bb5-9067-446e-af5d-8d2cfc1eebed.png)

  Ajout d'un champ complémentaite caché pour savoir sur quelle version on est.
  On garde le même fonctionnement qu'avant lorsque cette option n'est pas activée.

  Lorsque cette option est activée :
  - On garde le même comportement lorsqu'on archive une version (ex : si on est sur la version 1 et qu'on archive alors on passe au numéro de version suivante)
  - Lorsqu'on restaure une version alors on garde son numéro au lieu de restaurer sur la version courante
  - Par contre lorsqu'on supprime une version alors on ne bouche pas les trous pour pouvoir se repérer avec les numéros de version


## Version 2.4

- FIX : Editor name *03/08/2022* - 2.4.1
- NEW : restore public note and extra fields  - *29/07/2022* - 2.4.0[PR# Open-DSI](https://github.com/ATM-Consulting/dolibarr_module_propalehistory/pull/55)

## Version 2.3

- FIX : Compatibility PHP 8 - *15/07/2022* - 2.3.3
- FIX : Module icon *12/07/2022* 2.3.2
- FIX : FIX keep parent line id on restore proposal *11/07/2022* 2.3.1 [PR#49 Open-DSI](https://github.com/ATM-Consulting/dolibarr_module_propalehistory/pull/49)
- NEW : Setup use Dolibarr V15 setup  *11/07/2022* 2.3.0
- NEW : Ajout de la class TechATM pour l'affichage de la page "A propos" *10/05/2022* 2.2.0
- NEW : Reset proposal dates on archiving - 2.1.0 - *04/10/2021*  
  Need hidden conf PROPALEHISTORY_ARCHIVE_AND_RESET_DATES set to 1
  by activating this conf, reset proposal date and end validity date with today date if above const is enabled


## Version 2.0
- FIX : v16 token - *02/06/2022* - 2.0.5  
- FIX : hack stockage de la ref propale - 2.0.4 - *13/05/2022*
- FIX: Suppression de conf pour ajouter son comportement par défaut - 2.0.3 - *04/04/2022*
- FIX: token  - 2.0.2 - *16/03/2022*
- FIX: v14 compatibility - setDateLivraison -> setDeliveryDate - 2.0.1 - *27/07/2021*
- NEW: compatible with Dolibarr v13 and v14, **no longer compatible with v11 and lower** - *2021-06-28* - 2.0.0

## Version 1.4

- FIX - Compatibility V14 : Edit the descriptor: editor_name, editor_url and family - *2021-06-10* - 1.4.7


## Version 1.0

initial version
