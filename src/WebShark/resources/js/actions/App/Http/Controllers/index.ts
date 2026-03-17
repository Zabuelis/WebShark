import PcapController from './PcapController'
import Settings from './Settings'

const Controllers = {
    PcapController: Object.assign(PcapController, PcapController),
    Settings: Object.assign(Settings, Settings),
}

export default Controllers