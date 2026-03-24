import {
  MAT_SELECT_CONFIG,
  MAT_SELECT_SCROLL_STRATEGY,
  MAT_SELECT_SCROLL_STRATEGY_PROVIDER,
  MAT_SELECT_SCROLL_STRATEGY_PROVIDER_FACTORY,
  MAT_SELECT_TRIGGER,
  MatSelect,
  MatSelectChange,
  MatSelectModule,
  MatSelectTrigger
} from "./chunk-K4R7UYOP.js";
import "./chunk-FFYSLRO2.js";
import {
  MatOptgroup,
  MatOption
} from "./chunk-SXHMGGBC.js";
import "./chunk-M4XCC673.js";
import "./chunk-SJX2U5JM.js";
import "./chunk-2YNEIYH4.js";
import "./chunk-YS4XRZYO.js";
import "./chunk-OKDMU7LP.js";
import {
  MatError,
  MatFormField,
  MatHint,
  MatLabel,
  MatPrefix,
  MatSuffix
} from "./chunk-FHZQQWQR.js";
import "./chunk-U3DZYMHM.js";
import "./chunk-NINIMCLP.js";
import "./chunk-SXJSF4GY.js";
import "./chunk-XZMU7HLE.js";
import "./chunk-7PFBEBDI.js";
import "./chunk-WLTZAGGD.js";
import "./chunk-VENV3F3G.js";
import "./chunk-GWFLKVBH.js";
import "./chunk-5EG33CFQ.js";
import "./chunk-OFKK22LP.js";
import "./chunk-S5FMLKQD.js";
import "./chunk-IEEGG2AB.js";
import "./chunk-KLINWHDZ.js";
import "./chunk-II5TYR5U.js";
import "./chunk-DIEF43WA.js";
import "./chunk-2ZFTCAES.js";
import "./chunk-WHIVADGR.js";
import "./chunk-JRFR6BLO.js";
import "./chunk-HWYXSU2G.js";
import "./chunk-MARUHEWW.js";
import "./chunk-WDMUDEB6.js";

// node_modules/@angular/material/fesm2022/select.mjs
var matSelectAnimations = {
  // Represents
  // trigger('transformPanel', [
  //   state(
  //     'void',
  //     style({
  //       opacity: 0,
  //       transform: 'scale(1, 0.8)',
  //     }),
  //   ),
  //   transition(
  //     'void => showing',
  //     animate(
  //       '120ms cubic-bezier(0, 0, 0.2, 1)',
  //       style({
  //         opacity: 1,
  //         transform: 'scale(1, 1)',
  //       }),
  //     ),
  //   ),
  //   transition('* => void', animate('100ms linear', style({opacity: 0}))),
  // ])
  /** This animation transforms the select's overlay panel on and off the page. */
  transformPanel: {
    type: 7,
    name: "transformPanel",
    definitions: [
      {
        type: 0,
        name: "void",
        styles: {
          type: 6,
          styles: { opacity: 0, transform: "scale(1, 0.8)" },
          offset: null
        }
      },
      {
        type: 1,
        expr: "void => showing",
        animation: {
          type: 4,
          styles: {
            type: 6,
            styles: { opacity: 1, transform: "scale(1, 1)" },
            offset: null
          },
          timings: "120ms cubic-bezier(0, 0, 0.2, 1)"
        },
        options: null
      },
      {
        type: 1,
        expr: "* => void",
        animation: {
          type: 4,
          styles: { type: 6, styles: { opacity: 0 }, offset: null },
          timings: "100ms linear"
        },
        options: null
      }
    ],
    options: {}
  }
};
export {
  MAT_SELECT_CONFIG,
  MAT_SELECT_SCROLL_STRATEGY,
  MAT_SELECT_SCROLL_STRATEGY_PROVIDER,
  MAT_SELECT_SCROLL_STRATEGY_PROVIDER_FACTORY,
  MAT_SELECT_TRIGGER,
  MatError,
  MatFormField,
  MatHint,
  MatLabel,
  MatOptgroup,
  MatOption,
  MatPrefix,
  MatSelect,
  MatSelectChange,
  MatSelectModule,
  MatSelectTrigger,
  MatSuffix,
  matSelectAnimations
};
//# sourceMappingURL=@angular_material_select.js.map
