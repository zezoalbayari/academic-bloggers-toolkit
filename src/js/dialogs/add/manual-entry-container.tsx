import { action, IObservableArray, IObservableValue, ObservableMap } from 'mobx';
import { observer } from 'mobx-react';
import * as React from 'react';

import Callout from 'components/callout';
import Spinner from 'components/spinner';
import AutoCite from './autocite';
import { MetaFields } from './meta-fields';
import { People } from './people';

interface ManualEntryProps {
    loading: boolean;
    manualData: ObservableMap<string>;
    people: IObservableArray<CSL.TypedPerson>;
    errorMessage: IObservableValue<string>;
    autoCite(kind: 'webpage' | 'book' | 'chapter', query: string): void;
    typeChange(citationType: string): void;
}

@observer
export default class ManualEntryContainer extends React.PureComponent<ManualEntryProps, {}> {
    static readonly labels = top.ABT_i18n.tinymce.referenceWindow.manualEntryContainer;
    static readonly citationTypes = top.ABT_i18n.citationTypes;

    @action dismissError = () => this.props.errorMessage.set('');

    handleTypeChange = (e: React.FormEvent<HTMLSelectElement>) => {
        this.props.typeChange(e.currentTarget.value);
    };

    handleWheel = (e: React.WheelEvent<HTMLDivElement>) => {
        const isScrollingDown = e.deltaY > 0;
        const isScrollingUp = !isScrollingDown;
        const isScrollable = e.currentTarget.scrollHeight > e.currentTarget.clientHeight;
        const atBottom =
            e.currentTarget.scrollHeight <=
            e.currentTarget.clientHeight + Math.ceil(e.currentTarget.scrollTop);
        const atTop = e.currentTarget.scrollTop === 0;

        if (isScrollable && !atBottom && isScrollingDown) {
            e.cancelable = false;
        }

        if (isScrollable && !atTop && isScrollingUp) {
            e.cancelable = false;
        }
    };

    render() {
        const itemType: string = this.props.manualData.get('type')!;
        const renderAutocite: boolean = ['webpage', 'book', 'chapter'].indexOf(itemType) > -1;
        return (
            <div>
                {this.props.loading && <Spinner size="40px" overlay />}
                <div id="type-select-row">
                    <label
                        htmlFor="type-select"
                        children={ManualEntryContainer.labels.citationType}
                    />
                    <select id="type-select" onChange={this.handleTypeChange} value={itemType}>
                        {ManualEntryContainer.citationTypes.map((item, i) =>
                            <option
                                key={i}
                                value={item.value}
                                aria-selected={itemType === item.value}
                                children={item.label}
                            />,
                        )}
                    </select>
                </div>
                {renderAutocite &&
                    itemType === 'webpage' &&
                    <AutoCite
                        getter={this.props.autoCite}
                        kind={itemType as 'webpage'}
                        placeholder={ManualEntryContainer.labels.URL}
                        inputType="url"
                    />}
                {renderAutocite &&
                    ['book', 'chapter'].indexOf(itemType) > -1 &&
                    <AutoCite
                        getter={this.props.autoCite}
                        kind={itemType as 'book' | 'chapter'}
                        placeholder={ManualEntryContainer.labels.ISBN}
                        pattern="(?:[\dxX]-?){10}|(?:[\dxX]-?){13}"
                        inputType="text"
                    />}
                <div
                    onWheel={this.handleWheel}
                    className={renderAutocite ? 'bounded-rect autocite' : 'bounded-rect'}
                >
                    <Callout children={this.props.errorMessage.get()} dismiss={this.dismissError} />
                    {this.props.manualData.get('type') !== 'article' &&
                        <People
                            people={this.props.people}
                            citationType={this.props.manualData.get('type') as CSL.CitationType}
                        />}
                    <MetaFields meta={this.props.manualData} />
                </div>
                <style jsx>{`
                    label {
                        margin-right: 10px;
                    }
                    select {
                        flex: auto;
                    }
                    #type-select-row {
                        display: flex;
                        padding: 0 10px 10px;
                        align-items: center;
                    }
                    .bounded-rect {
                        max-height: calc(100vh - 200px);
                        overflow-y: auto;
                        overflow-x: hidden;
                    }
                    .autocite {
                        max-height: calc(100vh - 250px);
                    }
                `}</style>
            </div>
        );
    }
}
