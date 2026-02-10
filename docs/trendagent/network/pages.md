## Страницы → сетевые запросы

Этот файл агрегирует основную связь «тип страницы → API‑эндпоинты → фильтры».

---

### Objects — list / table / plans / map

| Route            | UI‑тип        | Основные сущности   | Основные запросы                                                                                                      | Фильтры (источники)                                             |
|------------------|--------------|---------------------|-----------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------|
| `/objects/list`  | list + cards | `Block`, `Apartment`| `POST api.trendagent.ru/v4_29/blocks/search`, `POST .../blocks/search/count`, `GET apartment-api.trendagent.ru/v1/directories` | `directories` из apartment-api (`rooms`, `deadlines`, `locations`, ...) |
| `/objects/table` | table        | `Block`             | те же, что для `/objects/list`                                                                                       | те же                                                            |
| `/objects/plans` | plans grid   | `Apartment`, `Block`| `POST api.trendagent.ru/v4_29/apartments/search`, `GET apartment-api.trendagent.ru/v1/directories`                  | справочники квартир (`rooms`, `finishings`, `contracts`, ...)   |
| `/objects/map`   | map+sidebar  | `Block`             | `POST api.trendagent.ru/v4_29/blocks/search`, `GET .../blocks/search/count`, `GET .../blocks/{id}/geo/buildings`    | те же, что для list/table                                       |

---

### Parkings — list / table / map / detail

| Route                 | UI‑тип        | Основные сущности           | Основные запросы                                                                                   | Фильтры (источники)                                |
|-----------------------|--------------|-----------------------------|----------------------------------------------------------------------------------------------------|---------------------------------------------------|
| `/parkings/list`      | list + cards | `ParkingBlock`, `ParkingPlace` | `GET parkings-api.trendagent.ru/search/blocks`, `GET parkings-api.trendagent.ru/enums/*`, `GET .../directories/*` | enums и directories parkings-api (`parking_types`, ...) |
| `/parkings/table`     | table        | `ParkingBlock`              | те же search + enums                                                                              | те же                                             |
| `/parkings/map`       | map+sidebar  | `ParkingBlock`              | `GET .../search/blocks` + дополнительные поля локаций                                             | те же                                             |
| `/parkingplace/{id}`  | detail       | `ParkingPlace`, `ParkingBlock`, `Block` | detail‑endpoint parkings-api (точный URL не зафиксирован; см. `parkings-api.md`)                  | без доп. фильтров, только загрузка detail         |

---

### Commerce — list / table / plan / map / detail

| Route                       | UI‑тип        | Основные сущности      | Основные запросы                                                                                               | Фильтры (источники)                                |
|-----------------------------|--------------|------------------------|----------------------------------------------------------------------------------------------------------------|---------------------------------------------------|
| `/commerce/list`           | list + cards | `CommercePremise`      | `GET commerce-api.trendagent.ru/filters`, `GET .../search/{BLOCK_ID}/premises` (при фикс. объекте или наборе) | `filters` commerce-api (`purposes`, `property_types`, ...) |
| `/commerce/table`          | table        | `CommercePremise`      | те же search‑запросы                                                                                            | те же                                             |
| `/commerce/plan`           | plans grid   | `CommercePremise`      | `GET .../search/{BLOCK_ID}/premises` с иным набором полей для карточек                                         | те же                                             |
| `/commerce/map`            | map+sidebar  | `Block`, `CommercePremise` | `GET commerce-api.trendagent.ru/search/map/buildings`, `GET .../filters`                                      | те же                                             |
| `/commerce-premise/{id}`   | detail       | `CommercePremise`, `Block` | detail commerce‑endpoint (см. `commerce-api.md`, точный URL не зафиксирован)                                   | без фильтров; только загрузка detail              |

---

### Houses / Villages / HouseProjects

| Route              | UI‑тип                 | Основные сущности | Основные запросы                                                                                   | Фильтры (источники)                                    |
|--------------------|------------------------|-------------------|----------------------------------------------------------------------------------------------------|-------------------------------------------------------|
| `/houses/list`     | list + cards           | `Block`           | `POST api.trendagent.ru/v4_29/blocks/search`                                                       | подмножество apartment‑directories (`building_types`, ...) |
| `/houses/table`    | table                  | `Block`           | те же                                                                                              | те же                                                  |
| `/houses/plans`    | plans grid (если есть) | `Apartment`       | `POST api.trendagent.ru/v4_29/apartments/search`                                                   | те же, что для квартир                                |
| `/houses/map`      | map+sidebar            | `Block`           | `POST .../blocks/search`, `GET .../blocks/{id}/geo/buildings`                                      | те же                                                  |
| `/villages/list`   | list + cards           | `Village`/`Block` | `POST api.trendagent.ru/v4_29/blocks/search`                                                       | локационные/региональные справочники apartment-api   |
| `/villages/plots`  | table / list           | `Plot`, `Village` | detail‑/search‑запросы по участкам (конкретные endpoints в логах не были зафиксированы текстом)   | специализированные справочники по земле (логически)  |
| `/villages/map`    | map+sidebar            | `Village`/`Block` | `POST .../blocks/search` + геоданные                                                               | те же                                                  |
| `/village/{slug}`  | detail                 | `Village`, `Plot` | detail‑endpoint по посёлку (точный URL не зафиксирован), возможна дополнительная выборка участков | без фильтров UI, лишь переключение вкладок           |
| `/houseprojects`   | list / grid            | `HouseProject`    | специализированные endpoints не были зафиксированы; UI построен на агрегированных данных блоков   | фильтры из apartment‑directories                      |
| `/houseproject/{slug}` | detail             | `HouseProject`    | detail‑endpoint (не зафиксирован), вероятное использование тех же directories                     | без фильтров                                          |

---

### Object detail и вкладки

| Route                                            | UI‑тип                | Основные сущности                     | Основные запросы                                                                            |
|--------------------------------------------------|----------------------|---------------------------------------|---------------------------------------------------------------------------------------------|
| `/object/{slug}`                                | detail + tabs        | `Block`, агрегаты                     | набор `GET api.trendagent.ru/v4_29/blocks/{id}/*`, `rewards-api`, `files`, `video`, `contacts`, `3d-tour` |
| `/object/{slug}/checkerboard`                   | checkerboard grid    | `Block`, `Apartment`                  | см. detail блока + доп. выборка квартир (конкретные endpoints шахматки не зафиксированы)   |
| `/object/{slug}/flat/{id}`                      | unit detail          | `Apartment`, `Block`                  | `GET api.trendagent.ru/v4_29/apartments/search` (для списка) + detail‑вызов по id (не зафиксирован) |
| `/object/{slug}#parkings`                       | detail tab           | `Block`, `ParkingBlock`, `ParkingPlace` | комбинация core‑detail + `parkings-api` (см. `parkings-api.md`)                             |
| `/object/{slug}#commerce`                       | detail tab           | `Block`, `CommercePremise`            | комбинация core‑detail + `commerce-api`                                                    |

---

### Статус покрытия

- Для всех ключевых типов страниц list/table/map/plans/detail зафиксированы:
  - **route**, **UI‑тип**, **главные сущности**, **основные API‑домен(ы)**.
- Для отдельных detail‑эндпоинтов (unit‑квартиры, конкретные участки, часть villages/houseprojects):
  - известно только, что они существуют и к какому домену относятся;
  - **точный URL и полный JSON‑ответ не зафиксированы** и требуют отдельного будущего просмотра Network.

